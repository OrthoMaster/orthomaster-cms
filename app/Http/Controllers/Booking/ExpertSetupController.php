<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\ExpertAvailabilityException;
use App\Models\ExpertAvailabilityRule;
use App\Models\ExpertBookingSetting;
use App\Models\ExpertOffer;
use App\Services\Booking\ExpertBookingSetupService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CMS : l'admin consulte et modifie la configuration de rendez-vous de tous
 * les experts, avec les mêmes possibilités que l'expert dans l'application.
 * Toute la logique métier passe par ExpertBookingSetupService.
 */
class ExpertSetupController extends Controller
{
    /** Onglets de la page d'un expert ; le premier est ouvert par défaut. */
    public const TABS = ['settings', 'offers', 'availability'];

    public function __construct(private ExpertBookingSetupService $setup)
    {
    }

    /* ===================== LISTE ===================== */

    /**
     * Liste des experts et état de leur configuration.
     * GET /booking/experts
     */
    public function index(Request $request)
    {
        $offersCount = DB::table('expert_offers')
            ->selectRaw('COUNT(*)')
            ->whereColumn('expert_offers.expert_id', 'e.id')
            ->where('active_flg', 1)
            ->whereNull('deleted_at');

        $rulesCount = DB::table('expert_availability_rules')
            ->selectRaw('COUNT(*)')
            ->whereColumn('expert_availability_rules.expert_id', 'e.id');

        $experts = DB::table('experts as e')
            ->leftJoin('expert_booking_settings as s', 's.expert_id', '=', 'e.id')
            ->select('e.id', 'e.name')
            ->selectRaw('COALESCE(s.enabled_flg, 0) as enabled_flg')
            ->selectSub($offersCount, 'offers_count')
            ->selectSub($rulesCount, 'rules_count')
            ->when($request->filled('q'), fn ($q) => $q->where('e.name', 'like', '%' . $request->input('q') . '%'))
            ->when($request->input('status') === 'enabled', fn ($q) => $q->where('s.enabled_flg', 1))
            ->when($request->input('status') === 'disabled', fn ($q) => $q->whereRaw('COALESCE(s.enabled_flg, 0) = 0'))
            ->orderBy('e.name')
            ->get();

        return view('booking.experts.index', compact('experts'));
    }

    /* ===================== FICHE EXPERT ===================== */

    /**
     * Fiche d'un expert : Réglages, Offres, Disponibilités.
     * GET /booking/experts/{expertId}?tab=
     */
    public function show(Request $request, int $expertId)
    {
        $expert   = $this->findExpert($expertId);
        $settings = ExpertBookingSetting::where('expert_id', $expertId)->first();
        $resolved = $this->setup->resolved($expertId);
        $defaults = ExpertBookingSetupService::systemDefaults();

        $offers = ExpertOffer::where('expert_id', $expertId)
            ->withCount('images')
            ->orderBy('order_dsp')
            ->orderBy('id')
            ->get();

        $rules = ExpertAvailabilityRule::where('expert_id', $expertId)
            ->orderBy('weekday')
            ->orderByRaw("FIELD(period, 'morning', 'afternoon')")
            ->orderBy('valid_from')
            ->get();

        // Les exceptions passées n'ont plus d'effet ; inutile de les afficher.
        $today      = Carbon::now($resolved['timezone'])->toDateString();
        $exceptions = ExpertAvailabilityException::where('expert_id', $expertId)
            ->where('date', '>=', $today)
            ->orderBy('date')
            ->orderByRaw('period IS NOT NULL')
            ->orderByRaw("FIELD(period, 'morning', 'afternoon')")
            ->get();

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : self::TABS[0];

        return view('booking.experts.show', compact(
            'expert', 'settings', 'resolved', 'defaults', 'offers', 'rules', 'exceptions', 'tab'
        ));
    }

    /**
     * PUT /booking/experts/{expertId}/settings
     */
    public function updateSettings(Request $request, int $expertId): RedirectResponse
    {
        $this->findExpert($expertId);

        $this->setup->saveSettings($expertId, $request->only([
            'timezone',
            'min_notice_days',
            'max_advance_months',
            'morning_start',
            'morning_end',
            'afternoon_start',
            'afternoon_end',
        ]) + [
            // Une case décochée n'est pas envoyée : on la lit explicitement.
            'enabled_flg' => $request->boolean('enabled_flg'),
        ]);

        return $this->backTo($expertId, 'settings', 'Réglages enregistrés.');
    }

    /* ===================== OFFRES ===================== */

    /**
     * POST /booking/experts/{expertId}/offers
     */
    public function storeOffer(Request $request, int $expertId): RedirectResponse
    {
        $this->findExpert($expertId);

        $this->setup->saveOffer($expertId, $this->offerInput($request));

        return $this->backTo($expertId, 'offers', 'Offre créée.');
    }

    /**
     * PUT /booking/experts/{expertId}/offers/{offer}
     */
    public function updateOffer(Request $request, int $expertId, ExpertOffer $offer): RedirectResponse
    {
        $this->assertOwned($offer->expert_id, $expertId);

        $this->setup->saveOffer($expertId, $this->offerInput($request), $offer);

        return $this->backTo($expertId, 'offers', 'Offre mise à jour.');
    }

    /**
     * DELETE /booking/experts/{expertId}/offers/{offer}
     */
    public function destroyOffer(int $expertId, ExpertOffer $offer): RedirectResponse
    {
        $this->assertOwned($offer->expert_id, $expertId);

        $this->setup->deleteOffer($offer);

        return $this->backTo($expertId, 'offers', 'Offre supprimée.');
    }

    private function offerInput(Request $request): array
    {
        return $request->only(['title', 'description', 'blocks_count', 'price', 'order_dsp']) + [
            'active_flg' => $request->boolean('active_flg'),
        ];
    }

    /* ===================== DISPONIBILITÉS ===================== */

    /**
     * POST /booking/experts/{expertId}/rules
     */
    public function storeRules(Request $request, int $expertId): RedirectResponse
    {
        $this->findExpert($expertId);

        $result = $this->setup->addRules($expertId, $request->only(['weekdays', 'periods', 'valid_from', 'valid_until']));

        $message = sprintf('%d créneau(x) ajouté(s).', $result['created']);
        if ($result['skipped'] > 0) {
            $message .= sprintf(' %d déjà existant(s), ignoré(s).', $result['skipped']);
        }

        return $this->backTo($expertId, 'availability', $message);
    }

    /**
     * DELETE /booking/experts/{expertId}/rules/{rule}
     */
    public function destroyRule(int $expertId, ExpertAvailabilityRule $rule): RedirectResponse
    {
        $this->assertOwned($rule->expert_id, $expertId);

        $this->setup->deleteRule($rule);

        return $this->backTo($expertId, 'availability', 'Créneau supprimé.');
    }

    /**
     * POST /booking/experts/{expertId}/exceptions
     */
    public function storeExceptions(Request $request, int $expertId): RedirectResponse
    {
        $this->findExpert($expertId);

        $count = $this->setup->addExceptions($expertId, $request->only(['date_from', 'date_to', 'period', 'type']));

        return $this->backTo($expertId, 'availability', sprintf('%d date(s) enregistrée(s).', $count));
    }

    /**
     * DELETE /booking/experts/{expertId}/exceptions/{exception}
     */
    public function destroyException(int $expertId, ExpertAvailabilityException $exception): RedirectResponse
    {
        $this->assertOwned($exception->expert_id, $expertId);

        $this->setup->deleteException($exception);

        return $this->backTo($expertId, 'availability', 'Exception supprimée.');
    }

    /* ===================== OUTILS ===================== */

    private function findExpert(int $expertId): object
    {
        $expert = DB::table('experts')->where('id', $expertId)->first(['id', 'name', 'user_id']);
        abort_unless($expert, 404);

        return $expert;
    }

    /** Empêche de modifier l'élément d'un autre expert en changeant l'URL. */
    private function assertOwned(int $ownerId, int $expertId): void
    {
        abort_unless($ownerId === $expertId, 404);
    }

    private function backTo(int $expertId, string $tab, string $message): RedirectResponse
    {
        return redirect()
            ->route('booking.experts.show', ['expertId' => $expertId, 'tab' => $tab])
            ->with(['message' => $message, 'alert-type' => 'success']);
    }
}