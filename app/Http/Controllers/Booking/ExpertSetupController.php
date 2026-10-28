<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\ExpertAvailabilityException;
use App\Models\ExpertAvailabilityRule;
use App\Models\ExpertBookingSetting;
use App\Models\ExpertLocation;
use App\Models\ExpertOffer;
use App\Models\ExpertOfferDate;
use App\Models\ExpertOfferMode;
use App\Models\ExpertOfferSlot;
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
    /**
     * Onglets de la page d'un expert ; le premier est ouvert par défaut.
     * Ordre de saisie naturel : lieux, puis grille (qui y fait référence), puis offres.
     */
    public const TABS = ['settings', 'locations', 'availability', 'offers'];

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
     * Fiche d'un expert : Réglages, Lieux, Disponibilités, Offres.
     * GET /booking/experts/{expertId}?tab=
     */
    public function show(Request $request, int $expertId)
    {
        $expert   = $this->findExpert($expertId);
        $settings = ExpertBookingSetting::where('expert_id', $expertId)->first();
        $resolved = $this->setup->resolved($expertId);
        $defaults = ExpertBookingSetupService::systemDefaults();
        $today    = Carbon::now($resolved['timezone'])->toDateString();

        // Lieux actifs d'abord ; les archivés restent visibles pour pouvoir les réactiver.
        $locations = ExpertLocation::where('expert_id', $expertId)
            ->withCount('rules')
            ->orderByRaw('archived_at IS NOT NULL')
            ->orderBy('label')
            ->get();

        // Adresse figée tant qu'un rendez-vous en attente ou à venir s'y tient.
        $lockedLocationIds = $this->setup->lockedLocationIds($locations->pluck('id')->all());

        $offers = ExpertOffer::where('expert_id', $expertId)
            ->with('modes')
            ->withCount('images')
            ->orderBy('order_dsp')
            ->orderBy('id')
            ->get();

        // Calendrier de chaque offre, indexé « offre|mode » : de quoi pré-remplir le formulaire.
        $offerIds    = $offers->pluck('id');
        $offerSlots  = ExpertOfferSlot::whereIn('offer_id', $offerIds)->get()
            ->groupBy(fn ($s) => $s->offer_id . '|' . $s->mode)
            ->map(fn ($group) => $group->pluck('key_string')->all());
        $offerDates  = ExpertOfferDate::whereIn('offer_id', $offerIds)->where('date', '>=', $today)
            ->orderBy('date')->orderByRaw("FIELD(period, 'morning', 'afternoon')")->get()
            ->groupBy(fn ($d) => $d->offer_id . '|' . $d->mode)
            ->map(fn ($group) => $group->pluck('key_string')->all());

        $rules = ExpertAvailabilityRule::where('expert_id', $expertId)
            ->with('location')
            ->orderBy('weekday')
            ->orderByRaw("FIELD(period, 'morning', 'afternoon')")
            ->orderBy('valid_from')
            ->get();

        // Les exceptions passées n'ont plus d'effet ; inutile de les afficher.
        $exceptions = ExpertAvailabilityException::where('expert_id', $expertId)
            ->where('date', '>=', $today)
            ->orderBy('date')
            ->orderByRaw('period IS NOT NULL')
            ->orderByRaw("FIELD(period, 'morning', 'afternoon')")
            ->get();

        // Ce que le formulaire d'offre peut proposer, par mode.
        $grid      = $this->setup->gridSlots($expertId);
        $gridDates = collect(ExpertOfferMode::MODE_LABELS)
            ->map(fn ($label, $mode) => $this->setup->gridDates($expertId, $mode))
            ->all();

        $modeLabels     = ExpertOfferMode::MODE_LABELS;
        $scheduleLabels = ExpertOfferMode::SCHEDULE_LABELS;

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : self::TABS[0];

        return view('booking.experts.show', compact(
            'expert', 'settings', 'resolved', 'defaults', 'locations', 'lockedLocationIds', 'offers', 'offerSlots', 'offerDates',
            'rules', 'exceptions', 'grid', 'gridDates', 'modeLabels', 'scheduleLabels', 'today', 'tab'
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

    /* ===================== LIEUX ===================== */

    /**
     * POST /booking/experts/{expertId}/locations
     */
    public function storeLocation(Request $request, int $expertId): RedirectResponse
    {
        $this->findExpert($expertId);

        $this->setup->saveLocation($expertId, $this->locationInput($request));

        return $this->backTo($expertId, 'locations', 'Lieu ajouté.');
    }

    /**
     * PUT /booking/experts/{expertId}/locations/{location}
     */
    public function updateLocation(Request $request, int $expertId, ExpertLocation $location): RedirectResponse
    {
        $this->assertOwned($location->expert_id, $expertId);

        $this->setup->saveLocation($expertId, $this->locationInput($request), $location);

        return $this->backTo($expertId, 'locations', 'Lieu mis à jour.');
    }

    /**
     * POST /booking/experts/{expertId}/locations/{location}/archive
     */
    public function archiveLocation(int $expertId, ExpertLocation $location): RedirectResponse
    {
        $this->assertOwned($location->expert_id, $expertId);

        $detached = $this->setup->archiveLocation($location);

        $message = 'Lieu archivé.';
        if ($detached > 0) {
            $message .= sprintf(' %d demi-journée(s) de la grille n\'ont plus de lieu (en ligne uniquement).', $detached);
        }

        return $this->backTo($expertId, 'locations', $message);
    }

    /**
     * POST /booking/experts/{expertId}/locations/{location}/restore
     */
    public function restoreLocation(int $expertId, ExpertLocation $location): RedirectResponse
    {
        $this->assertOwned($location->expert_id, $expertId);

        $this->setup->restoreLocation($location);

        return $this->backTo($expertId, 'locations', 'Lieu réactivé. Rattachez-le aux demi-journées voulues dans Disponibilités.');
    }

    private function locationInput(Request $request): array
    {
        return $request->only([
            'label', 'address_line', 'postal_code', 'city', 'country', 'lat', 'lng', 'google_place_id', 'access_details',
        ]);
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

    /**
     * modes[online|onsite][enabled|price|schedule_type|book_ahead_days|slots[]|dates[]]
     */
    private function offerInput(Request $request): array
    {
        $modes = [];
        foreach (array_keys(ExpertOfferMode::MODE_LABELS) as $mode) {
            $raw = (array) $request->input("modes.$mode", []);
            // Case décochée : non envoyée ; prix saisi avec une virgule à la française.
            $raw['enabled'] = $request->boolean("modes.$mode.enabled");
            if (isset($raw['price'])) {
                $raw['price'] = str_replace([' ', ','], ['', '.'], (string) $raw['price']);
            }
            $modes[$mode] = $raw;
        }

        return $request->only(['title', 'description', 'blocks_count', 'order_dsp']) + [
            'active_flg' => $request->boolean('active_flg'),
            'modes'      => $modes,
        ];
    }

    /* ===================== DISPONIBILITÉS ===================== */

    /**
     * POST /booking/experts/{expertId}/rules
     */
    public function storeRules(Request $request, int $expertId): RedirectResponse
    {
        $this->findExpert($expertId);

        $result = $this->setup->addRules(
            $expertId,
            $request->only(['weekdays', 'periods', 'location_id', 'valid_from', 'valid_until'])
        );

        $message = sprintf('%d créneau(x) ajouté(s).', $result['created']);
        if ($result['skipped'] > 0) {
            $message .= sprintf(' %d déjà existant(s), ignoré(s).', $result['skipped']);
        }

        return $this->backTo($expertId, 'availability', $message);
    }

    /**
     * Change le lieu d'une demi-journée de la grille (vide : en ligne seulement).
     * PUT /booking/experts/{expertId}/rules/{rule}/location
     */
    public function updateRuleLocation(Request $request, int $expertId, ExpertAvailabilityRule $rule): RedirectResponse
    {
        $this->assertOwned($rule->expert_id, $expertId);

        $this->setup->setRuleLocation($rule, $request->filled('location_id') ? (int) $request->input('location_id') : null);

        return $this->backTo($expertId, 'availability', 'Lieu de la demi-journée mis à jour.');
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