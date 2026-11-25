<?php

namespace App\Services\Satisfaction;

use App\Mail\SatisfactionSurveyMail;
use App\Models\SatisfactionSurvey;
use App\Status;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Questionnaires de satisfaction « à froid » (Qualiopi ind. 30).
 *
 * Un questionnaire = un apprenant × un devis signé (carts) : l'apprenant qui achète une deuxième
 * formation reçoit un deuxième questionnaire. Envoi 3 mois après le devis, une relance à 6 mois
 * si pas de réponse.
 */
class SatisfactionSurveyService
{
    /** Premier envoi : mois après la date du devis. */
    public const SEND_AFTER_MONTHS = 3;

    /** Relance : mois après la date du devis. */
    public const REMIND_AFTER_MONTHS = 6;

    /** Écart minimal entre l'envoi et la relance : l'historique (devis > 6 mois) ne reçoit pas les deux à la suite. */
    public const REMIND_MIN_DAYS_AFTER_SEND = 30;

    /**
     * Tâche quotidienne : seulement les devis qui atteignent 3 mois à partir de la mise en service.
     * Les devis plus anciens (historique) partent une fois, à la main (satisfaction:send --run).
     */
    public const DAILY_SINCE = '2026-08-25';

    /* ===================== CRÉATION ===================== */

    /**
     * Crée les questionnaires manquants à partir des devis signés actifs. Rejouable sans doublon.
     * Retourne le nombre de questionnaires créés.
     */
    public function sync(): int
    {
        // Un panier peut donner plusieurs devis (découpage par durée, un par apprenant en FIFPL) : on garde le premier.
        $carts = DB::table('contracts')
            ->where('type', 'signed')
            ->where('status', Status::ACTIVE->value)
            ->whereNotNull('cart_id')
            ->groupBy('cart_id')
            ->selectRaw('cart_id, MIN(created_at) as signed_at, MIN(no) as contract_no, MIN(organization) as organization, MIN(beneficiary_id) as beneficiary_id')
            ->get();

        $existing = SatisfactionSurvey::query()
            ->whereNotNull('cart_id')
            ->get(['cart_id', 'user_id'])
            ->map(fn ($s) => $s->cart_id . '-' . $s->user_id)
            ->flip();

        $created = 0;

        foreach ($carts as $cart) {
            $packages = $this->cartPackages($cart->cart_id);
            if ($packages->isEmpty()) {
                continue;
            }

            foreach ($this->cartLearners($cart) as $userId) {
                if ($existing->has($cart->cart_id . '-' . $userId)) {
                    continue;
                }

                SatisfactionSurvey::create([
                    'token'          => Str::random(32),
                    'user_id'        => $userId,
                    'cart_id'        => $cart->cart_id,
                    'contract_no'    => $cart->contract_no,
                    'organization'   => $cart->organization,
                    'purchased_at'   => $cart->signed_at,
                    'package_ids'    => $packages->pluck('id')->all(),
                    'packages_label' => $packages->pluck('label')->implode("\n"),
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Apprenants du panier ; sans membre (cas individuel), le bénéficiaire du devis.
     */
    private function cartLearners(object $cart): array
    {
        $ids = DB::table('cart_user')
            ->join('users', 'users.id', '=', 'cart_user.user_id')
            ->where('cart_user.cart_id', $cart->cart_id)
            ->distinct()
            ->pluck('cart_user.user_id')
            ->all();

        if (!$ids && $cart->beneficiary_id && DB::table('users')->where('id', $cart->beneficiary_id)->exists()) {
            $ids = [$cart->beneficiary_id];
        }

        return $ids;
    }

    private function cartPackages(int $cartId)
    {
        return DB::table('cart_items as ci')
            ->join('packages as p', 'p.id', '=', 'ci.package_id')
            ->leftJoin('trainings as t', 't.id', '=', 'p.training_id')
            ->where('ci.cart_id', $cartId)
            ->select('p.id')
            ->selectRaw("CASE WHEN t.name IS NULL OR t.name = p.name THEN p.name ELSE CONCAT(t.name, ' — ', p.name) END as label")
            ->distinct()
            ->orderBy('p.id')
            ->get();
    }

    /* ===================== ENVOI ===================== */

    /**
     * Crée les questionnaires manquants puis envoie premiers envois et relances dus.
     * $report reçoit une ligne par e-mail (envoyé, ou qui le serait en simulation).
     */
    public function process(bool $dryRun, bool $daily, ?callable $report = null): array
    {
        // Simulation : les questionnaires sont créés dans une transaction annulée, pour voir ce qui partirait.
        if ($dryRun) {
            DB::beginTransaction();
        }

        try {
            $created = $this->sync();
            $rows = [];

            foreach ($this->dueFirstSends($daily) as $survey) {
                $rows[] = $this->deliver($survey, false, $dryRun, $report);
            }

            foreach ($this->dueReminders() as $survey) {
                $rows[] = $this->deliver($survey, true, $dryRun, $report);
            }
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }
        }

        return ['created' => $created, 'rows' => $rows];
    }

    private function dueFirstSends(bool $daily)
    {
        return SatisfactionSurvey::with('user')
            ->where('is_fake', false)
            ->whereNull('sent_at')
            ->whereNull('answered_at')
            ->where('purchased_at', '<=', now()->subMonths(self::SEND_AFTER_MONTHS))
            ->when($daily, fn ($q) => $q->where('purchased_at', '>=', Carbon::parse(self::DAILY_SINCE)))
            ->orderBy('purchased_at')
            ->get();
    }

    private function dueReminders()
    {
        return SatisfactionSurvey::with('user')
            ->where('is_fake', false)
            ->whereNotNull('sent_at')
            ->whereNull('reminded_at')
            ->whereNull('answered_at')
            ->where('purchased_at', '<=', now()->subMonths(self::REMIND_AFTER_MONTHS))
            ->where('sent_at', '<=', now()->subDays(self::REMIND_MIN_DAYS_AFTER_SEND))
            ->orderBy('purchased_at')
            ->get();
    }

    private function deliver(SatisfactionSurvey $survey, bool $reminder, bool $dryRun, ?callable $report): array
    {
        $row = [
            'survey'    => $survey->id,
            'email'     => $survey->user?->email,
            'devis'     => $survey->contract_no,
            'date'      => $survey->purchased_at->format('d/m/Y'),
            'kind'      => $reminder ? 'relance' : 'envoi',
            'error'     => null,
        ];

        if (!$row['email']) {
            $row['error'] = 'pas d\'e-mail';
        } elseif (!$dryRun) {
            try {
                Mail::to($row['email'])->send(new SatisfactionSurveyMail($survey, $reminder));
                $survey->forceFill([$reminder ? 'reminded_at' : 'sent_at' => now()])->save();
            } catch (\Throwable $e) {
                Log::error($e->getMessage(), ['satisfaction-send', $survey->id]);
                $row['error'] = $e->getMessage();
            }
        }

        if ($report) {
            $report($row);
        }

        return $row;
    }

    /**
     * Envoi immédiat depuis le CMS, sans attendre les 3 mois.
     * Premier e-mail → sent_at ; s'il a déjà reçu le questionnaire, l'e-mail part comme relance.
     */
    public function sendNow(SatisfactionSurvey $survey): void
    {
        if ($survey->is_fake) {
            throw new \RuntimeException('Questionnaire fictif : aucun e-mail.');
        }
        if ($survey->answered_at) {
            throw new \RuntimeException('Cet apprenant a déjà répondu.');
        }
        if (!$survey->user?->email) {
            throw new \RuntimeException('Cet apprenant n\'a pas d\'e-mail.');
        }

        $reminder = $survey->sent_at !== null;
        Mail::to($survey->user->email)->send(new SatisfactionSurveyMail($survey, $reminder));
        $survey->forceFill([$reminder ? 'reminded_at' : 'sent_at' => now()])->save();
    }

    /* ===================== RÉPONSE ===================== */

    /**
     * Enregistre la réponse ; le score est la moyenne des notes 1–5.
     */
    public function answer(SatisfactionSurvey $survey, array $data): void
    {
        $ratings = array_map(fn ($col) => (int) $data[$col], array_keys(SatisfactionSurvey::RATINGS));

        $survey->forceFill(array_merge(
            array_intersect_key($data, SatisfactionSurvey::RATINGS),
            [
                'score'             => round(array_sum($ratings) / count($ratings), 2),
                'practice'          => $data['practice'],
                'recommend'         => (int) $data['recommend'],
                'difficulties_flg'  => (bool) $data['difficulties_flg'],
                'difficulties_text' => $data['difficulties_flg'] ? ($data['difficulties_text'] ?? null) : null,
                'liked_text'        => $data['liked_text'] ?? null,
                'suggestions_text'  => $data['suggestions_text'] ?? null,
                'recontact_flg'     => !empty($data['recontact_flg']),
                'answered_at'       => now(),
            ]
        ))->save();
    }
}
