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
     * GET /api/products/count?filters[]=...
     */
    public function count(Request $request): JsonResponse
    {
        $products = $this->matchingProducts($request);
        return response()->json(['count' => count($products)]);
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
            'manufacturer'     => optional($p->manufacturer)->name,
            'category'         => $p->category,
            'images'           => $this->normalizeImages($p->images ?? []),
            'tags'             => $p->relationLoaded('filters')
                ? $p->filters->pluck('filter_key')->all()
                : [],
            'description_tabs' => $p->description_tabs ?? [],
            'sizes'            => $this->normalizeSizes($p->sizes ?? []),
        ];
    }
 
    /**
     * Normalize sizes JSON to a stable shape with the keys the client
     * expects. New schema is nested:
     *   {
     *     "dimensions": [
     *       {
     *         "name": "Tour de taille",
     *         "options": [
     *           { "label": "S", "range": "65-80 cm", "filter_keys": ["taille-s"] },
     *           { "label": "M", "range": "Var.",
     *             "variants": [
     *               { "filter_keys": ["taille-m","homme"], "range": "32-36 cm" },
     *               { "filter_keys": ["taille-s","femme"], "range": "20 cm" }
     *             ]
     *           }
     *         ]
     *       }
     *     ]
     *   }
     *
     * Filters/normalizes:
     *   - dimensions without a name or without options → dropped
     *   - options without a label → dropped
     *   - empty filter_keys / variants → omitted from output
     *
     * Returns null when nothing useful remained (so the client knows to
     * hide the "Tailles" tab entirely).
     */
    private function normalizeSizes($sizes): ?array
    {
        if (!is_array($sizes)) return null;
        $rawDims = $sizes['dimensions'] ?? null;
        if (!is_array($rawDims)) return null;
 
        $outDims = [];
        foreach ($rawDims as $dim) {
            if (!is_array($dim)) continue;
            $name = isset($dim['name']) ? trim((string) $dim['name']) : '';
            if ($name === '') continue;
 
            $outOpts = [];
            foreach (($dim['options'] ?? []) as $opt) {
                if (!is_array($opt)) continue;
                $label = isset($opt['label']) ? trim((string) $opt['label']) : '';
                if ($label === '') continue;
 
                $row = ['label' => $label];
                $row['range'] = isset($opt['range']) ? (string) $opt['range'] : '';
 
                // Flat filter_keys
                $flat = [];
                foreach (($opt['filter_keys'] ?? []) as $k) {
                    if (is_string($k) && $k !== '') $flat[] = $k;
                }
                if (!empty($flat)) $row['filter_keys'] = array_values(array_unique($flat));
 
                // Variants
                $variants = [];
                foreach (($opt['variants'] ?? []) as $v) {
                    if (!is_array($v)) continue;
                    $vKeys = [];
                    foreach (($v['filter_keys'] ?? []) as $k) {
                        if (is_string($k) && $k !== '') $vKeys[] = $k;
                    }
                    if (empty($vKeys)) continue;
                    $variants[] = [
                        'filter_keys' => array_values(array_unique($vKeys)),
                        'range'       => isset($v['range']) ? (string) $v['range'] : '',
                    ];
                }
                if (!empty($variants)) $row['variants'] = $variants;
 
                $outOpts[] = $row;
            }
 
            if (empty($outOpts)) continue;
            $outDims[] = ['name' => $name, 'options' => $outOpts];
        }
 
        if (empty($outDims)) return null;
        return ['dimensions' => $outDims];
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
     * Strategy:
     *   - No filters → return all products via SQL.
     *   - With filters → fetch a candidate set via SQL, then refine with
     *     productMatches() per group. This is more flexible than a pure
     *     SQL whereIn because the per-group rule needs to inspect each
     *     product's full tag set.
     *
     * The SQL pre-filter pulls products that share at least one tag with
     * the expanded filter set (parents included). Products fully outside
     * any filter group are still candidates — they'll pass productMatches
     * (rule 2: no tag in group = no restriction).
     *
     * For dataset sizes up to a few thousand products this is fine. If
     * scale grows, push more logic to SQL or switch to a search engine.
     */
    private function matchingProducts(Request $request): array
    {
        $raw = $this->parseFilterParam($request);

        $query = Product::query()->with(['manufacturer', 'filters']);

        if (empty($raw)) {
            return $query->orderBy('name')->get()->all();
        }

        // Pre-filter candidates that have at least one relevant tag, OR
        // have no tags in any of the user's filter groups at all (which
        // would still match per rule 2). The simplest approach: pull
        // everything and filter in PHP. Add SQL pre-filter only if
        // performance becomes an issue.
        $candidates = $query->orderBy('name')->get();

        $userFilters = $raw;

        return $candidates
            ->filter(function (Product $p) use ($userFilters) {
                $tags = $p->filters->pluck('filter_key')->all();
                return $this->rules->productMatches($tags, $userFilters);
            })
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
}
