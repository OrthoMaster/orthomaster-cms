<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\Brochures\BrochureDraft;
use App\Models\Domain;
use App\Models\Expert;
use App\Models\ExpertLocation;
use App\Models\ExpertOffer;
use App\Models\ExpertOfferImage;
use App\Models\ExpertOfferMode;
use App\Services\Booking\ExpertBookingSetupService;
use App\Services\Booking\ExpertSlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * API de l'application : l'utilisateur parcourt les experts réservables,
 * leurs offres (en ligne / en présentiel) et les demi-journées libres d'une offre.
 *
 * « Réservable » = réservation activée + au moins une offre active avec un mode actif
 * + au moins une règle récurrente encore valable.
 */
class BookingExpertController extends Controller
{
    public function __construct(private ExpertSlotService $slots, private ExpertBookingSetupService $setup)
    {
    }

    /* ===================== EXPERTS ===================== */

    /** Taille d'une page de la liste : l'application charge la suite en fin de défilement. */
    public const PER_PAGE = 10;

    /**
     * Tous les experts sauf l'expert connecté : ceux qui acceptent des demandes en tête,
     * puis les autres, par nom. Recherche sur le nom, le titre, les références et les domaines.
     * Chaque expert réservable porte la liste courte de ses offres (repliable dans la liste).
     * GET /booking/experts?page=1&q=
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'q'    => ['nullable', 'string', 'max:100'],
        ]);

        $page   = (int) $request->input('page', 1);
        $search = trim((string) $request->input('q', ''));
        $userId = $request->user()->id;
        [$onlineSql, $onlineBindings] = $this->onlineExpression();

        $query = DB::table('experts as e')
            ->leftJoin('expert_booking_settings as s', 's.expert_id', '=', 'e.id')
            // Sans compte, un expert (simple auteur de contenus) ne peut pas recevoir de demande.
            ->whereNotNull('e.user_id')
            ->where('e.user_id', '!=', $userId)
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where(fn ($w) => $w
                    ->where('e.name', 'like', $like)
                    ->orWhere('e.honorific', 'like', $like)
                    ->orWhere('e.credentials', 'like', $like)
                    ->orWhereIn('e.id', Expert::query()
                        ->whereHas('domains', fn ($d) => $d->where('label', 'like', $like))
                        ->select('id')));
            });

        $total = (clone $query)->count();

        $rows = $query
            ->select('e.id')
            ->selectRaw("{$onlineSql} as is_online", $onlineBindings)
            ->orderByDesc('is_online')
            ->orderBy('e.name')
            ->forPage($page, self::PER_PAGE)
            ->get();

        // Ordre et statut en query builder, puis models chargés en une requête pour l'avatar S3.
        $models = Expert::whereIn('id', $rows->pluck('id'))->get()->keyBy('id');

        // Offres des experts réservables de la page, en une requête.
        $onlineIds = $rows->filter(fn ($row) => $row->is_online)->pluck('id')->all();
        $offersByExpert = $onlineIds
            ? ExpertOffer::active()
                ->whereIn('expert_id', $onlineIds)
                ->whereHas('activeModes')
                ->with('modes')
                ->orderBy('order_dsp')
                ->orderBy('id')
                ->get()
                ->groupBy('expert_id')
            : collect();

        $experts = $rows
            ->filter(fn ($row) => $models->has($row->id))
            ->map(fn ($row) => self::expertPayload($models[$row->id], false) + [
                'online' => (bool) $row->is_online,
                'offers' => $offersByExpert->get($row->id, collect())
                    ->map(fn (ExpertOffer $offer) => self::offerSummary($offer))
                    ->values(),
            ])
            ->values();

        return $this->success([
            'experts'    => $experts,
            'pagination' => [
                'page'     => $page,
                'per_page' => self::PER_PAGE,
                'total'    => $total,
                'has_more' => $page * self::PER_PAGE < $total,
            ],
        ]);
    }

    /**
     * Fiche d'un expert. Un expert qui ne prend pas de rendez-vous reste consultable
     * (il apparaît dans la liste) : online = false et aucune offre.
     * Chaque mode en présentiel donne les lieux où l'offre peut se tenir (3b).
     * GET /booking/experts/{expertId}
     */
    public function show(int $expertId): JsonResponse
    {
        $expert = Expert::with('domains')->whereNotNull('user_id')->findOrFail($expertId);
        $online = $this->isBookable($expertId);
        $bounds = $this->slots->bounds($expertId);

        $offers = $online
            ? ExpertOffer::active()
                ->whereHas('activeModes')
                ->with(['images', 'modes'])
                ->where('expert_id', $expertId)
                ->orderBy('order_dsp')
                ->orderBy('id')
                ->get()
                ->map(fn (ExpertOffer $offer) => $this->publicOffer($offer))
                ->values()
            : collect();

        return $this->success([
            'expert'     => self::expertPayload($expert) + ['online' => $online],
            'timezone'   => $bounds['timezone'],
            'first_date' => $bounds['first_date'],
            'last_date'  => $bounds['last_date'],
            'offers'     => $offers,
        ]);
    }

    /* ===================== CALENDRIER ===================== */

    /**
     * Demi-journées libres d'une offre dans un mode, regroupées par jour ; en présentiel,
     * chaque demi-journée porte son lieu. Sans offre : grille générale de l'expert
     * (ancien comportement, gardé pour les versions de l'application pas encore à jour).
     * Sans dates : à partir du premier jour réservable, sur un mois.
     * GET /booking/experts/{expertId}/availability?offer_id=&mode=online|onsite&from=Y-m-d&to=Y-m-d
     */
    public function availability(Request $request, int $expertId): JsonResponse
    {
        $this->findBookable($expertId);

        $request->validate([
            'offer_id' => ['nullable', 'integer'],
            'mode'     => ['required_with:offer_id', Rule::in(array_keys(ExpertOfferMode::MODE_LABELS))],
            'from'     => ['nullable', 'date_format:Y-m-d'],
            'to'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $offerMode = null;
        if ($request->filled('offer_id')) {
            $offer = ExpertOffer::active()->where('expert_id', $expertId)->with('modes')->find($request->input('offer_id'));
            $offerMode = $offer?->activeMode($request->input('mode'));
            abort_unless($offerMode, 404, "Ce thème n'est plus proposé sous cette forme.");
        }

        $blocks = $this->slots->availableBlocks($expertId, $request->input('from'), $request->input('to'), false, $offerMode);

        return $this->success(self::calendarPayload($this->slots->bounds($expertId), $blocks));
    }

    /* ===================== FORMATS PARTAGÉS ===================== */

    /**
     * Forme commune d'un expert. Domaines dans l'ordre défini par la position du pivot.
     * Le slug suffit : l'application compose l'adresse de la page vitrine web.
     * Sans domaines pour la liste : ils n'y sont pas affichés, inutile de les charger.
     */
    public static function expertPayload(Expert $expert, bool $withDomains = true): array
    {
        $payload = [
            'id'            => $expert->id,
            'honorific'     => $expert->honorific,
            'name'          => $expert->name,
            'credentials'   => $expert->credentials,
            'lede'          => $expert->lede,
            'slug'          => $expert->slug,
            'avatar_url'    => $expert->avatar_url,
            'palette'       => $expert->palette,
            // La page vitrine n'existe pour le public qu'une fois publiée.
            'showcase_published' => $expert->brochure_published_at !== null,
            // Couleur résolue côté serveur : une seule source pour la liste des palettes.
            'palette_color' => BrochureDraft::PALETTES[$expert->palette][1] ?? null,
        ];

        if ($withDomains) {
            $payload['domains'] = $expert->domains
                ->map(fn ($domain) => ['id' => $domain->id, 'name' => $domain->label])
                ->values();
        }

        return $payload;
    }

    /**
     * Liste commune des domaines, pour le choix dans le profil expert.
     * GET /booking/domains
     */
    public function domains(): JsonResponse
    {
        $domains = Domain::orderBy('sort_order')->orderBy('label')->get(['id', 'label'])
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->label])
            ->values();

        return $this->success(['domains' => $domains]);
    }

    /**
     * Forme commune d'une offre, réutilisée par l'API expert. Seuls les modes actifs
     * sont listés ; la relation modes doit être chargée.
     */
    public static function offerPayload(ExpertOffer $offer): array
    {
        $modes = $offer->modes->where('active_flg', true)->values();

        return [
            'id'             => $offer->id,
            'title'          => $offer->title,
            'description'    => $offer->description,
            'blocks_count'   => $offer->blocks_count,
            'duration_label' => $offer->duration_label,
            // Prix le plus bas des modes actifs : sert aux écrans qui n'affichent qu'un prix.
            'price'          => $modes->isEmpty() ? null : (float) $modes->min('price'),
            'currency'       => $offer->currency,
            'modes'          => $modes->map(fn (ExpertOfferMode $m) => self::modePayload($m))->values(),
            'images'         => $offer->images->map(fn (ExpertOfferImage $image) => $image->payload())->values(),
        ];
    }

    /** Un mode d'offre : prix TTC de toute l'offre et type de calendrier. */
    public static function modePayload(ExpertOfferMode $mode): array
    {
        return [
            'mode'            => $mode->mode,
            'label'           => $mode->mode_label,
            // decimal revient en chaîne ; l'application attend un nombre.
            'price'           => (float) $mode->price,
            'schedule_type'   => $mode->schedule_type,
            'book_ahead_days' => $mode->book_ahead_days,
        ];
    }

    /** Ligne courte d'une offre sous l'expert dans la liste. */
    private static function offerSummary(ExpertOffer $offer): array
    {
        $modes = $offer->modes->where('active_flg', true);

        return [
            'id'             => $offer->id,
            'title'          => $offer->title,
            'duration_label' => $offer->duration_label,
            'modes'          => $modes->pluck('mode')->values(),
            'price_from'     => $modes->isEmpty() ? null : (float) $modes->min('price'),
            'currency'       => $offer->currency,
        ];
    }

    /** Offre telle que la voit le demandeur : modes actifs, lieux pour le présentiel. */
    private function publicOffer(ExpertOffer $offer): array
    {
        $payload  = self::offerPayload($offer);
        $byMode   = $offer->modes->keyBy('mode');

        $payload['modes'] = collect($payload['modes'])->map(function (array $mode) use ($byMode, $offer) {
            $mode['locations'] = $this->setup
                ->offerLocations($byMode[$mode['mode']], $offer->expert_id)
                ->map(fn (ExpertLocation $l) => $l->publicPayload())
                ->values();

            return $mode;
        })->values();

        return $payload;
    }

    /**
     * Calendrier regroupé par jour, réutilisé par l'aperçu de l'expert.
     * start_at / end_at en UTC (ISO 8601) pour l'affichage dans le fuseau de l'appareil ;
     * start_local / end_local dans le fuseau de l'expert, pour le libellé « Matin 09:00 – 12:00 ».
     * location : lieu de la demi-journée en présentiel (label court + ville), sinon null.
     */
    public static function calendarPayload(array $bounds, Collection $blocks): array
    {
        $days = $blocks
            ->groupBy('date')
            ->map(fn (Collection $dayBlocks, string $date) => [
                'date'   => $date,
                'blocks' => $dayBlocks->map(fn (array $block) => [
                    'period'      => $block['period'],
                    'start_at'    => $block['start_at']->toIso8601ZuluString(),
                    'end_at'      => $block['end_at']->toIso8601ZuluString(),
                    'start_local' => $block['start_local'],
                    'end_local'   => $block['end_local'],
                    'location'    => !empty($block['location']) ? [
                        'id'    => $block['location']->id,
                        'label' => $block['location']->label,
                        'city'  => $block['location']->city,
                    ] : null,
                ])->values(),
            ])
            ->values();

        return [
            'timezone'   => $bounds['timezone'],
            'first_date' => $bounds['first_date'],
            'last_date'  => $bounds['last_date'],
            'days'       => $days,
        ];
    }

    /* ===================== OUTILS ===================== */

    /** Enveloppe commune des API de l'application : { success, data }. */
    private function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    /**
     * « En ligne » = réservation activée + au moins une offre active avec un mode actif
     * + au moins une règle encore valable. Une seule expression SQL pour le tri de la liste
     * et les contrôles.
     *
     * @return array{0:string, 1:array}
     */
    private function onlineExpression(): array
    {
        $sql = '(COALESCE(s.enabled_flg, 0) = 1
            AND EXISTS (SELECT 1 FROM expert_offers o
                JOIN expert_offer_modes m ON m.offer_id = o.id AND m.active_flg = 1
                WHERE o.expert_id = e.id AND o.active_flg = 1 AND o.deleted_at IS NULL)
            AND EXISTS (SELECT 1 FROM expert_availability_rules r
                WHERE r.expert_id = e.id AND (r.valid_until IS NULL OR r.valid_until >= ?)))';

        return [$sql, [now()->toDateString()]];
    }

    private function isBookable(int $expertId): bool
    {
        [$sql, $bindings] = $this->onlineExpression();

        return DB::table('experts as e')
            ->leftJoin('expert_booking_settings as s', 's.expert_id', '=', 'e.id')
            ->where('e.id', $expertId)
            ->whereRaw($sql, $bindings)
            ->exists();
    }

    private function findBookable(int $expertId): void
    {
        abort_unless($this->isBookable($expertId), 404, "Cet expert n'accepte pas de rendez-vous pour le moment.");
    }
}