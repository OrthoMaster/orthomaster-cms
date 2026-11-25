<?php

namespace App\Services\Satisfaction;

use App\Models\SatisfactionSurvey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Questionnaires fictifs pour la démonstration du suivi qualité : vrais apprenants et vrais paquets
 * (package_user), réponses inventées, résultat global bon. Marqués is_fake : jamais envoyés,
 * supprimés par clear(). Les données réelles (package_user, questionnaires réels) ne sont pas touchées.
 */
class SatisfactionFakeDataService
{
    /** Part des questionnaires fictifs choisis parmi les paquets avancés (progress > COMPLETION_MIN) : le taux de complétion reste bon. */
    private const COMPLETED_SHARE = 0.85;

    /** Part des questionnaires fictifs envoyés qui ont une réponse. */
    private const ANSWERED_SHARE = 0.72;

    /** Tirages pondérés : valeur => poids. */
    private const RATING_WEIGHTS = [5 => 55, 4 => 34, 3 => 8, 2 => 2, 1 => 1];
    private const PRACTICE_WEIGHTS = ['regular' => 45, 'occasional' => 36, 'not_yet' => 14, 'no' => 5];
    private const RECOMMEND_WEIGHTS = [10 => 34, 9 => 28, 8 => 20, 7 => 9, 6 => 4, 5 => 3, 4 => 2];

    private const LIKED = [
        'Les vidéos sont claires et très concrètes.',
        'Les cas cliniques, directement applicables au comptoir.',
        'Pouvoir avancer à mon rythme sur mon téléphone.',
        'La qualité des intervenants et leurs explications.',
        'Les quiz qui permettent de vérifier ses acquis.',
        'Le format court des leçons, facile à caser dans la journée.',
        'Les rappels d\'anatomie avant chaque module.',
        'Très complet sur la prise de mesures et le choix des orthèses.',
        'Les fiches récapitulatives, que je ressors régulièrement.',
        'J\'ai gagné en assurance face aux patients.',
    ];

    private const SUGGESTIONS = [
        'Ajouter davantage de cas pratiques en fin de module.',
        'Proposer des fiches PDF téléchargeables.',
        'Un module complémentaire sur la contention veineuse serait le bienvenu.',
        'Pouvoir accélérer la vitesse des vidéos.',
        'Plus de questions de quiz pour s\'entraîner.',
        'Des sessions de questions-réponses en direct avec les intervenants.',
        'Rien à redire, continuez ainsi !',
    ];

    private const DIFFICULTIES = [
        'Quelques coupures de vidéo avec une connexion faible.',
        'J\'ai eu du mal à retrouver ma progression sur l\'ordinateur.',
        'Connexion difficile la première fois.',
        'Certains termes techniques mériteraient une explication.',
    ];

    /**
     * Crée $count questionnaires fictifs. Retourne le nombre réellement créé
     * (moins si la base n'a pas assez d'inscriptions).
     */
    public function generate(int $count): int
    {
        $pairs = $this->enrolments();
        if ($pairs->isEmpty()) {
            return 0;
        }

        [$done, $pending] = $pairs->partition(fn ($p) => (int) $p->progress > SatisfactionSurvey::COMPLETION_MIN)->all();
        $done = $done->shuffle()->values();
        $pending = $pending->shuffle()->values();
        $labels = $this->packageLabels($pairs->pluck('package_id')->unique());
        $byUser = $pairs->groupBy('user_id');

        $created = 0;
        $usedUsers = [];

        while ($created < $count && ($done->isNotEmpty() || $pending->isNotEmpty())) {
            $pool = ($done->isNotEmpty() && (mt_rand() / mt_getrandmax() < self::COMPLETED_SHARE || $pending->isEmpty())) ? $done : $pending;
            $pair = $pool->shift();

            // Un apprenant = un questionnaire fictif, pour que la liste ressemble à de vraies réponses.
            if (isset($usedUsers[$pair->user_id])) {
                continue;
            }
            $usedUsers[$pair->user_id] = true;

            // Parfois deux paquets achetés ensemble, comme dans un vrai devis.
            $packageIds = [$pair->package_id];
            $other = $byUser[$pair->user_id]->where('package_id', '!=', $pair->package_id)->first();
            if ($other && mt_rand(1, 100) <= 25) {
                $packageIds[] = $other->package_id;
            }

            SatisfactionSurvey::create($this->fakeSurvey($pair->user_id, $packageIds, $labels));
            $created++;
        }

        return $created;
    }

    /**
     * Supprime tous les questionnaires fictifs.
     */
    public function clear(): int
    {
        return SatisfactionSurvey::where('is_fake', true)->delete();
    }

    /* ===================== SOURCES RÉELLES ===================== */

    /**
     * Inscriptions réelles (apprenant × paquet) des utilisateurs normaux, hors comptes clonés.
     */
    private function enrolments(): Collection
    {
        return DB::table('package_user as pu')
            ->join('users as u', 'u.id', '=', 'pu.user_id')
            ->join('packages as p', 'p.id', '=', 'pu.package_id')
            ->whereIn('u.role_id', [2, 4])
            ->whereNotNull('u.email')
            ->where(fn ($q) => $q->whereNull('u.created_from')->orWhere('u.created_from', 'not like', 'CLONE%'))
            ->get(['pu.user_id', 'pu.package_id', 'pu.progress']);
    }

    private function packageLabels(Collection $packageIds): Collection
    {
        return DB::table('packages as p')
            ->leftJoin('trainings as t', 't.id', '=', 'p.training_id')
            ->whereIn('p.id', $packageIds)
            ->selectRaw("p.id, CASE WHEN t.name IS NULL OR t.name = p.name THEN p.name ELSE CONCAT(t.name, ' — ', p.name) END as label")
            ->pluck('label', 'id');
    }

    /* ===================== RÉPONSES INVENTÉES ===================== */

    private function fakeSurvey(int $userId, array $packageIds, Collection $labels): array
    {
        // Devis entre 2 ans et 3 mois avant aujourd'hui ; e-mail à 3 mois ; réponse dans les 3 semaines.
        $purchasedAt = Carbon::now()->subMonths(3)->subDays(mt_rand(0, 640))->setTime(mt_rand(8, 18), mt_rand(0, 59));
        $sentAt = $purchasedAt->copy()->addMonths(3)->setTime(10, 30);
        $answeredAt = $sentAt->copy()->addHours(mt_rand(1, 21 * 24));
        $answered = mt_rand() / mt_getrandmax() < self::ANSWERED_SHARE && $answeredAt->isPast();

        $base = [
            'is_fake'        => true,
            'token'          => Str::random(32),
            'user_id'        => $userId,
            'cart_id'        => null,
            'contract_no'    => 'DEMO-' . $purchasedAt->format('ym') . '-' . str_pad((string) $userId, 3, '0', STR_PAD_LEFT),
            'organization'   => ['FIFPL', 'OPCO-EP'][mt_rand(0, 1)],
            'purchased_at'   => $purchasedAt,
            'package_ids'    => $packageIds,
            'packages_label' => collect($packageIds)->map(fn ($id) => $labels[$id] ?? ('#' . $id))->implode("\n"),
            'sent_at'        => $sentAt,
        ];

        if (!$answered) {
            return $base;
        }

        // Une « humeur » par apprenant : ses notes se ressemblent, comme dans de vraies réponses.
        $mood = $this->pick(self::RATING_WEIGHTS);
        $ratings = [];
        foreach (array_keys(SatisfactionSurvey::RATINGS) as $col) {
            $ratings[$col] = mt_rand(1, 100) <= 70 ? $mood : $this->pick(self::RATING_WEIGHTS);
        }

        $difficulties = mt_rand(1, 100) <= 15;

        return $base + $ratings + [
            'score'             => round(array_sum($ratings) / count($ratings), 2),
            'practice'          => $this->pick(self::PRACTICE_WEIGHTS),
            'recommend'         => $mood >= 4 ? $this->pick(self::RECOMMEND_WEIGHTS) : mt_rand(4, 8),
            'difficulties_flg'  => $difficulties,
            'difficulties_text' => $difficulties ? self::DIFFICULTIES[array_rand(self::DIFFICULTIES)] : null,
            'liked_text'        => mt_rand(1, 100) <= 60 ? self::LIKED[array_rand(self::LIKED)] : null,
            'suggestions_text'  => mt_rand(1, 100) <= 40 ? self::SUGGESTIONS[array_rand(self::SUGGESTIONS)] : null,
            'recontact_flg'     => mt_rand(1, 100) <= 5,
            'answered_at'       => $answeredAt,
        ];
    }

    private function pick(array $weights): int|string
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
