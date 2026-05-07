<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\FilterRulesService;
use Illuminate\Database\Eloquent\Builder;
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
        $count = $this->matchingQuery($request)->count();
        return response()->json(['count' => $count]);
    }

    /**
     * GET /api/products?filters[]=...
     *
     * Slim list payload — name, manufacturer, category, first image only.
     */
    public function index(Request $request): JsonResponse
    {
        $products = $this->matchingQuery($request)
            ->with('manufacturer')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data'  => $products->map(fn($p) => $this->shapeListItem($p))->all(),
            'count' => $products->count(),
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
     * Full shape for the detail modal — every image, tag, and tab.
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
        ];
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
     * Build the query that filters products by the request's filter keys,
     * after expanding the keys with their parents from rules.json.
     *
     * Matching: a product matches if ANY of its tags is in the EXPANDED
     * filter set. This makes generic-tagged products (e.g. tagged 'age'
     * rather than 'age-u25') still match age-specific queries.
     *
     * No filters supplied → return all products.
     */
    private function matchingQuery(Request $request): Builder
    {
        $raw = $this->parseFilterParam($request);
        $query = Product::query();

        if (empty($raw)) {
            return $query;
        }

        $expanded = $this->rules->expandWithParents($raw);

        return $query->whereHas('filters', function (Builder $q) use ($expanded) {
            $q->whereIn('filter_key', $expanded);
        });
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
