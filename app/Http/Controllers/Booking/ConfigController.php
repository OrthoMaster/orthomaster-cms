<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\BookingCommissionRate;
use App\Models\BookingDeclineReason;
use App\Models\BookingPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CMS : configuration générale des rendez-vous (réservée à l'admin).
 *
 * Politique et commissions sont historisées : on n'efface jamais une ligne déjà
 * entrée en vigueur. Chaque réservation copiera les valeurs du jour de sa création,
 * donc modifier l'avenir ne touche jamais aux réservations ni aux factures passées.
 */
class ConfigController extends Controller
{
    public const TABS = ['policies', 'commissions', 'reasons'];

    /**
     * GET /booking/config?tab=
     */
    public function index(Request $request)
    {
        $today = now()->toDateString();

        $policies      = BookingPolicy::orderByDesc('effective_from')->get();
        $currentPolicy = BookingPolicy::currentFor($today);

        // Taux général d'abord, puis les taux par expert regroupés par nom.
        $commissions = DB::table('booking_commission_rates as r')
            ->leftJoin('experts as e', 'e.id', '=', 'r.expert_id')
            ->select('r.id', 'r.expert_id', 'r.rate', 'r.effective_from', 'e.name as expert_name')
            ->orderByRaw('r.expert_id IS NOT NULL')
            ->orderBy('e.name')
            ->orderByDesc('r.effective_from')
            ->get();

        $globalRate = BookingCommissionRate::rateFor(null, $today);
        $experts    = DB::table('experts')->orderBy('name')->get(['id', 'name']);
        $reasons    = BookingDeclineReason::orderBy('order_dsp')->orderBy('id')->get();

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : self::TABS[0];

        return view('booking.config.index', compact(
            'policies', 'currentPolicy', 'commissions', 'globalRate', 'experts', 'reasons', 'today', 'tab'
        ));
    }

    /* ===================== POLITIQUE ===================== */

    /**
     * POST /booking/config/policies
     */
    public function storePolicy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'effective_from'          => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'free_cancel_hours'       => ['required', 'integer', 'min:0', 'max:8760'],
            'late_cancel_penalty_pct' => ['required', 'numeric', 'between:0,100'],
            'user_noshow_refund_pct'  => ['required', 'numeric', 'between:0,100'],
        ], [], [
            'effective_from'          => "date d'effet",
            'free_cancel_hours'       => 'annulation gratuite',
            'late_cancel_penalty_pct' => 'pénalité',
            'user_noshow_refund_pct'  => 'remboursement no-show',
        ]);

        // Une seule politique par date : ressaisir une date la corrige au lieu
        // d'échouer sur l'index unique.
        BookingPolicy::updateOrCreate(['effective_from' => $data['effective_from']], $data);

        return $this->backTo('policies', 'Politique enregistrée.');
    }

    /**
     * DELETE /booking/config/policies/{policy}
     */
    public function destroyPolicy(BookingPolicy $policy): RedirectResponse
    {
        $today = now()->toDateString();

        if ($policy->effective_from->toDateString() < $today) {
            return $this->backTo('policies', 'Une politique déjà appliquée ne peut pas être supprimée.', 'error');
        }

        $remains = BookingPolicy::where('id', '!=', $policy->id)
            ->where('effective_from', '<=', $today)
            ->exists();
        if (!$remains) {
            return $this->backTo('policies', 'Il doit toujours rester une politique en vigueur.', 'error');
        }

        $policy->delete();

        return $this->backTo('policies', 'Politique supprimée.');
    }

    /* ===================== COMMISSIONS ===================== */

    /**
     * POST /booking/config/commissions
     */
    public function storeCommission(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'expert_id'      => ['nullable', 'integer', 'exists:experts,id'],
            'rate'           => ['required', 'numeric', 'between:0,100'],
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], [], [
            'expert_id'      => 'expert',
            'rate'           => 'taux',
            'effective_from' => "date d'effet",
        ]);

        BookingCommissionRate::updateOrCreate(
            ['expert_id' => $data['expert_id'] ?? null, 'effective_from' => $data['effective_from']],
            ['rate' => $data['rate']]
        );

        return $this->backTo('commissions', 'Taux enregistré.');
    }

    /**
     * DELETE /booking/config/commissions/{commission}
     */
    public function destroyCommission(BookingCommissionRate $commission): RedirectResponse
    {
        $today = now()->toDateString();

        if ($commission->effective_from->toDateString() < $today) {
            return $this->backTo('commissions', 'Un taux déjà appliqué ne peut pas être supprimé.', 'error');
        }

        // Sans taux général en vigueur, rateFor() renverrait 0 pour tout le monde.
        if ($commission->expert_id === null) {
            $remains = BookingCommissionRate::whereNull('expert_id')
                ->where('id', '!=', $commission->id)
                ->where('effective_from', '<=', $today)
                ->exists();
            if (!$remains) {
                return $this->backTo('commissions', 'Il doit toujours rester un taux général en vigueur.', 'error');
            }
        }

        $commission->delete();

        return $this->backTo('commissions', 'Taux supprimé.');
    }

    /* ===================== MOTIFS DE REFUS ===================== */

    /**
     * POST /booking/config/reasons
     */
    public function storeReason(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:150'],
        ], [], ['label' => 'libellé']);

        BookingDeclineReason::create([
            'label'      => $data['label'],
            'order_dsp'  => (int) BookingDeclineReason::max('order_dsp') + 1,
            'active_flg' => true,
        ]);

        return $this->backTo('reasons', 'Motif ajouté.');
    }

    /**
     * Pas de suppression : un motif utilisé par une réservation doit rester lisible.
     * PUT /booking/config/reasons/{reason}
     */
    public function updateReason(Request $request, BookingDeclineReason $reason): RedirectResponse
    {
        $data = $request->validate([
            'label'     => ['required', 'string', 'max:150'],
            'order_dsp' => ['required', 'integer', 'min:0', 'max:65535'],
        ], [], ['label' => 'libellé', 'order_dsp' => 'ordre']);

        $reason->update($data + ['active_flg' => $request->boolean('active_flg')]);

        return $this->backTo('reasons', 'Motif mis à jour.');
    }

    /* ===================== OUTILS ===================== */

    private function backTo(string $tab, string $message, string $type = 'success'): RedirectResponse
    {
        return redirect()
            ->route('booking.config.index', ['tab' => $tab])
            ->with(['message' => $message, 'alert-type' => $type]);
    }
}