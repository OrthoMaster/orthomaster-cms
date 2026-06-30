<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\FilterRulesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private FilterRulesService $rules,
    ) {}

    /**
     * GET /api/products/rules
     *
     * Returns the raw filter rules tree (rules.json). The RN app fetches
     * this on demand and caches via React Query.
     *
     * IMPORTANT: register this route BEFORE the /{product} wildcard, or
     * Laravel will route `rules` into show() instead of getRules().
     */
    public function getRules(): JsonResponse
    {
        return response()->json($this->loadFilterTree());
    }

    /**
     * GET /api/products/protocols
     *
     * Returns the protocols list (protocols.json). One entry per
     * treatment protocol: title + phases + groups (filter and info).
     * RN uses this to render the ProtocolScreen when the user's
     * pathology selection includes a child with a protocolId.
     *
     * Like /rules: register BEFORE the /{product} wildcard.
     */
    public function getProtocols(): JsonResponse
    {
        return response()->json($this->rules->loadProtocols());
    }

    /**
     * GET /api/products/count?filters[]=...
     */
    public function count(Request $request): JsonResponse
    {
        $products = $this->matchingProducts($request);
        return response()->json(['count' => count($products)]);
    }

    /**
     * GET /api/products/counts-by-group?filters[]=...&groups[]=g1,g2
     *
     * Returns the product count per group id, given the user's pathology
     * filter set. Used by the ProtocolScreen to disable groups that
     * would lead to an empty product list — saves the doctor a tap and
     * a "no results" screen.
     *
     * Strategy: one DB fetch, one pass over products. For each product
     * that passes the pathology+measurement check, bump the counter on
     * every group id it's tagged with. O(M products * G groups-per-
     * product) which is much cheaper than calling count() per group.
     *
     * IMPORTANT: register route BEFORE the /{product} wildcard.
     */
    public function countsByGroup(Request $request): JsonResponse
    {
        $raw      = $this->parseFilterParam($request);
        $groupIds = $this->parseGroupsParam($request);

        if (empty($groupIds)) {
            return response()->json((object) []);
        }

        $groupIdSet = array_flip($groupIds);
        $counts     = array_fill_keys($groupIds, 0);

        $candidates = Product::query()
            ->with(['manufacturer', 'filters'])
            ->get();

        foreach ($candidates as $product) {
            // Pathology + measurement match (no group filter — we want
            // to count BY group, not after group filter).
            if (!$this->rules->productMatches($product, $raw, null)) continue;

            $productGroups = $product->groups;
            if (!is_array($productGroups)) continue;

            foreach ($productGroups as $gid) {
                if (is_string($gid) && isset($groupIdSet[$gid])) {
                    $counts[$gid]++;
                }
            }
        }

        return response()->json($counts);
    }

    /**
     * GET /api/products?filters[]=...
     *
     * Slim list payload — name, manufacturer, category, first image only.
     */
    public function index(Request $request): JsonResponse
    {
        $products = $this->matchingProducts($request);

        return response()->json([
            'data'  => array_map(fn ($p) => $this->shapeListItem($p), $products),
            'count' => count($products),
        ]);
    }

    /**
     * GET /api/products/{product}
     *
     * Full payload for the detail modal.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['manufacturer', 'filters']);
        return response()->json([
            'data' => $this->shapeDetail($product),
        ]);
    }

    // ---------------------------------------------------------------
    // Response shaping (inline instead of Resource classes)
    // ---------------------------------------------------------------

    /**
     * Slim shape for list/grid views — drops description_tabs and
     * keeps only the cover image to keep payloads small.
     */
    private function shapeListItem(Product $p): array
    {
        return [
            'id'           => (string) $p->id,
            'name'         => $p->name,
            'sub'          => $p->sub,
            'manufacturer' => optional($p->manufacturer)->name,
            'category'     => $p->category,
            'image'        => $this->coverImage($p->images ?? []),
        ];
    }

    /**
     * Full shape for the detail modal — every image, tag, tab, and size.
     */
    private function shapeDetail(Product $p): array
    {
        return [
            'id'               => (string) $p->id,
            'name'             => $p->name,
            'sub'              => $p->sub,
            'manufacturer'     => optional($p->manufacturer)->name,
            'category'         => $p->category,
            'images'           => $this->normalizeImages($p->images ?? []),
            'tags'             => $p->relationLoaded('filters')
                ? $p->filters->pluck('filter_key')->all()
                : [],
            'description_tabs' => $p->description_tabs ?? [],
            'sizes'            => $this->normalizeSizes($p->sizes ?? []),
            'groups'           => is_array($p->groups) ? array_values($p->groups) : [],
        ];
    }

    /**
     * Normalize sizes JSON to the FLAT schema the client expects:
     *   {
     *     "options": [
     *       {
     *         "label": "Taille 1",
     *         "displayRange": "19-21 cm",
     *         "measurements": {
     *           "tour_de_cheville": { "min": 19, "max": 21, "displayRange": "19 cm - 21 cm" }
     *         }
     *       },
     *       ...
     *     ]
     *   }
     *
     * Filters/normalizes:
     *   - options without a label → dropped
     *   - measurements without numeric min/max → dropped (silent)
     *   - empty measurements object → option kept but with empty map
     *
     * Returns null when nothing useful remained (so the client knows to
     * hide the "Tailles" tab entirely).
     */
    private function normalizeSizes($sizes): ?array
    {
        if (!is_array($sizes)) return null;
        $rawOpts = $sizes['options'] ?? null;
        if (!is_array($rawOpts)) return null;

        $outOpts = [];
        foreach ($rawOpts as $opt) {
            if (!is_array($opt)) continue;
            $label = isset($opt['label']) ? trim((string) $opt['label']) : '';
            if ($label === '') continue;

            $row = ['label' => $label];
            $row['displayRange'] = isset($opt['displayRange'])
                ? (string) $opt['displayRange']
                : '';

            // Measurements — map keyed by dimensionKey
            $outMeasurements = [];
            $rawMeasurements = $opt['measurements'] ?? null;
            if (is_array($rawMeasurements)) {
                foreach ($rawMeasurements as $dimKey => $m) {
                    if (!is_string($dimKey) || $dimKey === '') continue;
                    if (!is_array($m)) continue;
                    if (!isset($m['min']) || !isset($m['max'])) continue;
                    if (!is_numeric($m['min']) || !is_numeric($m['max'])) continue;
                    $outMeasurements[$dimKey] = [
                        'min'          => (float) $m['min'],
                        'max'          => (float) $m['max'],
                        'displayRange' => isset($m['displayRange'])
                            ? (string) $m['displayRange']
                            : '',
                    ];
                }
            }
            $row['measurements'] = $outMeasurements;

            $outOpts[] = $row;
        }

        if (empty($outOpts)) return null;
        return ['options' => $outOpts];
    }

    /**
     * Images may be stored as ["url1", "url2"] or [{"url": "..."}, ...].
     * Normalize to a flat list of URL strings.
     */
    private function normalizeImages(array $images): array
    {
        return collect($images)
            ->map(fn($i) => is_array($i) ? ($i['url'] ?? null) : $i)
            ->filter()
            ->values()
            ->all();
    }

    private function coverImage(array $images): ?string
    {
        return $this->normalizeImages($images)[0] ?? null;
    }

    // ---------------------------------------------------------------
    // Query building
    // ---------------------------------------------------------------

    /**
     * Returns the list of matching Product models, ordered by name.
     *
     * Strategy: fetch all candidates eagerly with filters + sizes, then
     * refine in PHP via FilterRulesService::productMatches(). The match
     * logic combines pathology tag matching, measurement bucket overlap
     * with product.sizes, AND optional protocol-group membership.
     *
     * Filtering happens in PHP rather than SQL because the match rules
     * are too involved (visibility tree, parent inheritance, numeric
     * range overlap on sizes) to express cleanly as whereIn / whereJson
     * conditions. Up to a few thousand products this is fine.
     */
    private function matchingProducts(Request $request): array
    {
        $raw   = $this->parseFilterParam($request);
        $group = $this->parseGroupParam($request);

        $candidates = Product::query()
            ->with(['manufacturer', 'filters'])
            ->orderBy('name')
            ->get();

        // No filters and no group → everything.
        if (empty($raw) && $group === null) {
            return $candidates->all();
        }

        return $candidates
            ->filter(fn (Product $p) => $this->rules->productMatches($p, $raw, $group))
            ->values()
            ->all();
    }

    /**
     * Read filters[] from query string. Accepts both array format
     * (?filters[]=a&filters[]=b) and CSV (?filters=a,b) — the latter is
     * useful for QR codes where URL length matters.
     */
    private function parseFilterParam(Request $request): array
    {
        $param = $request->input('filters', []);

        if (is_string($param)) {
            $param = explode(',', $param);
        }
        if (!is_array($param)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn($v) => is_string($v) ? trim($v) : null, $param),
            fn($v) => $v !== null && $v !== ''
        ));
    }

    /**
     * Read the single `group` query string param — the id of a protocol
     * filter-group the doctor drilled into. Empty / missing → null.
     * Always one value; the protocol UI lets you view one group at a
     * time, so multi-value is not supported.
     */
    private function parseGroupParam(Request $request): ?string
    {
        $g = $request->input('group');
        if (!is_string($g)) return null;
        $g = trim($g);
        return $g !== '' ? $g : null;
    }

    /**
     * Read groups[] from query string — list of group ids to compute
     * counts for. Accepts both array (?groups[]=a&groups[]=b) and CSV
     * (?groups=a,b) formats, matching parseFilterParam's tolerance.
     */
    private function parseGroupsParam(Request $request): array
    {
        $param = $request->input('groups', []);

        if (is_string($param)) {
            $param = explode(',', $param);
        }
        if (!is_array($param)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn($v) => is_string($v) ? trim($v) : null, $param),
            fn($v) => $v !== null && $v !== ''
        ));
    }

    /**
     * Load the filter rules JSON from storage/product/rules.json.
     * Used by getRules() — match data live with the same file the
     * FilterRulesService reads, so the RN app never goes out of sync
     * with the matcher.
     */
    private function loadFilterTree(): array
    {
        $path = storage_path('app/product/rules.json');
        if (!file_exists($path)) return [];

        $raw = file_get_contents($path);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}