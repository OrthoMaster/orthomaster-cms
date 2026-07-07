<?php

namespace App\Http\Controllers;

use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * GET admin/products — list (Voyager browses normally; this is a fallback).
     */
    public function index(Request $request)
    {
        $query = Product::with('manufacturer');

        if ($request->filled('search_name')) {
            $query->where('name', 'like', '%' . $request->search_name . '%');
        }

        if ($request->filled('search_manufacturer')) {
            $query->where('manufacturer_id', $request->search_manufacturer);
        }

        $products = $query->orderBy('name')->paginate(20);

        return view('products.index', compact('products') + [
            'manufacturers' => Manufacturer::orderBy('name')->get(),
        ]);
    }

    /**
     * GET admin/products/create — show creation form.
     */
    public function create()
    {
        return view('products.create', $this->formData(new Product()));
    }

    /**
     * POST admin/products
     */
    public function store(Request $request)
    {
        $data = $this->validateInput($request);

        $product = DB::transaction(function () use ($data) {
            $product = Product::create([
                'name'             => $data['name'],
                'sub'              => $data['sub'],
                'manufacturer_id'  => $data['manufacturer_id'],
                'category'         => $data['category'],
                'images'           => $data['images'],
                'description_tabs' => $data['description_tabs'],
                'sizes'            => $data['sizes'],
                'groups'           => $data['groups'],
            ]);
            // product_filters is now PATHOLOGY-ONLY. Size buckets used to
            // get merged here too; they don't anymore — measurement
            // matching is via product.sizes[].measurements numeric ranges,
            // not via the filter_keys table. See FilterRulesService::measurementMatch.
            // Protocol groups live in their own JSON column on products,
            // not in product_filters — see column `groups` and
            // FilterRulesService::productMatches.
            $product->syncFilters($data['filter_keys']);
            return $product;
        });

        return redirect()
            ->route('products.edit', $product)
            ->with('message', 'Product created.')
            ->with('alert-type', 'success');
    }

    /**
     * GET admin/products/{product}/edit
     */
    public function edit(Request $request, string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return abort(404);
        }

        $product->load('filters');
        return view('products.edit', $this->formData($product));
    }

    /**
     * PUT/PATCH admin/products/{id}
     */
    public function update(Request $request, string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return abort(404);
        }

        $data = $this->validateInput($request);

        DB::transaction(function () use ($product, $data) {
            $product->update([
                'name'             => $data['name'],
                'sub'              => $data['sub'],
                'manufacturer_id'  => $data['manufacturer_id'],
                'category'         => $data['category'],
                'images'           => $data['images'],
                'description_tabs' => $data['description_tabs'],
                'sizes'            => $data['sizes'],
                'groups'           => $data['groups'],
            ]);
            $product->syncFilters($data['filter_keys']);
        });

        return redirect()
            ->route('products.edit', $product)
            ->with('message', 'Product updated.')
            ->with('alert-type', 'success');
    }

    /**
     * DELETE admin/products/{id}
     */
    public function destroy(string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return abort(404);
        }
        $product->delete();
        return back()
            ->with('message', 'Produit supprimé.')
            ->with('alert-type', 'success');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Build the bag of variables every form view needs:
     *   - product               (model instance, possibly empty for create)
     *   - manufacturers         (for the select)
     *   - filterTree            (decoded rules.json — full tree, used by the
     *                            pathology filters panel)
     *   - selectedKeys          (pathology filter keys already on this product)
     *   - measurementDimensions (list of {key, title} for the sizes form
     *                            dropdown so admin can pick which dimensionKey
     *                            a measurement column maps to)
     *   - protocolsTree         (decoded protocols.json — used by the
     *                            "Groupes de protocole" panel)
     *   - selectedGroups        (group ids already on this product)
     */
    private function formData(Product $product): array
    {
        $tree = $this->loadFilterTree();
        return [
            'product'               => $product,
            'manufacturers'         => Manufacturer::orderBy('name')->get(),
            'filterTree'            => $tree,
            'selectedKeys'          => $product->exists
                ? $product->filters->pluck('filter_key')->all()
                : [],
            'measurementDimensions' => $this->extractMeasurementDimensions($tree),
            'protocolsTree'         => $this->loadProtocols(),
            'selectedGroups'        => $product->exists
                ? (is_array($product->groups) ? $product->groups : [])
                : [],
        ];
    }

    /**
     * List every measurement node's (dimensionKey, title) — used by the
     * sizes form so admin can pick which dimension each option's
     * measurements belong to. Measurement nodes are identified by having
     * a top-level "dimensionKey" string field in rules.json.
     *
     * Returns:
     *   [
     *     { key: "tour_de_poignet", title: "Tour de poignet" },
     *     { key: "tour_de_cheville", title: "Tour de cheville" },
     *     ...
     *   ]
     */
    private function extractMeasurementDimensions(array $tree): array
    {
        $out = [];
        foreach ($tree as $node) {
            $dk = $node['dimensionKey'] ?? null;
            if (!is_string($dk) || $dk === '') continue;
            $out[] = [
                'key'   => $dk,
                'title' => $node['title'] ?? $dk,
            ];
        }
        return $out;
    }

    /**
     * Load the filter rules JSON from storage/product/rules.json.
     */
    private function loadFilterTree(): array
    {
        $path = storage_path('app/product/rules.json');
        if (!file_exists($path)) return [];

        $raw = file_get_contents($path);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Validate + normalize the form payload.
     *
     * Sizes schema is FLAT — a list of options with per-option numeric
     * measurements keyed by dimensionKey.
     *
     *   sizes_options[*][label]
     *   sizes_options[*][displayRange]
     *   sizes_options[*][measurements][<dimensionKey>][min]
     *   sizes_options[*][measurements][<dimensionKey>][max]
     *   sizes_options[*][measurements][<dimensionKey>][displayRange]
     *
     * filter_keys is PATHOLOGY ONLY — measurement bucket ids should not
     * appear here; they're filtered out post-validation just in case.
     */
    private function validateInput(Request $request): array
    {
        $validated = $request->validate([
            'name'                            => ['required', 'string', 'max:255'],
            'sub'                             => ['nullable', 'string', 'max:255'],
            'manufacturer_id'                 => ['required', 'integer', 'exists:manufacturers,id'],
            'category'                        => ['required', 'string', 'max:255'],
            'image_urls'                      => ['nullable', 'string'],
            'tabs'                            => ['nullable', 'array'],
            'tabs.*.title'                    => ['nullable', 'string', 'max:255'],
            'tabs.*.content'                  => ['nullable', 'string'],
            'filter_keys'                     => ['nullable', 'array'],
            'filter_keys.*'                   => ['string', 'max:64'],

            // Protocol groups — array of group ids from protocols.json.
            // Only filter-type groups are accepted; info groups and
            // unknown ids are stripped post-validation.
            'groups'                          => ['nullable', 'array'],
            'groups.*'                        => ['string', 'max:128'],

            // Flat sizes structure.
            'sizes_options'                                              => ['nullable', 'array'],
            'sizes_options.*.label'                                      => ['nullable', 'string', 'max:64'],
            'sizes_options.*.displayRange'                               => ['nullable', 'string', 'max:128'],
            'sizes_options.*.measurements'                               => ['nullable', 'array'],
            'sizes_options.*.measurements.*.min'                         => ['nullable', 'numeric'],
            'sizes_options.*.measurements.*.max'                         => ['nullable', 'numeric'],
            'sizes_options.*.measurements.*.displayRange'                => ['nullable', 'string', 'max:128'],
        ]);

        $images = [];
        if (!empty($validated['image_urls'])) {
            $images = collect(preg_split('/\r?\n/', $validated['image_urls']))
                ->map(fn($l) => trim($l))
                ->filter(fn($l) => $l !== '')
                ->values()
                ->all();
        }

        $tabs = collect($validated['tabs'] ?? [])
            ->map(fn($t) => [
                'title'   => trim($t['title']   ?? ''),
                'content' => $t['content'] ?? '',
            ])
            ->filter(fn($t) => $t['title'] !== '' || $t['content'] !== '')
            ->values()
            ->all();

        $sizes = $this->normalizeAndValidateSizes($validated['sizes_options'] ?? []);

        // Pathology filter keys only. Strip anything that looks like a
        // measurement bucket id (= present in rules.json under a node
        // with a dimensionKey) — those are not user-tickable filters.
        $bucketIds   = $this->collectBucketIds();
        $bucketIdSet = array_flip($bucketIds);

        $pathologyKeys = array_values(array_filter(
            array_unique($validated['filter_keys'] ?? []),
            fn($k) => is_string($k) && $k !== '' && !isset($bucketIdSet[$k]),
        ));

        // Protocol groups — keep only ids that exist in protocols.json
        // as filter-type groups. Info groups and unknown ids dropped.
        // Same defensive pattern as pathology vs measurement separation.
        $validGroupIds   = $this->collectFilterGroupIds();
        $validGroupIdSet = array_flip($validGroupIds);

        $groups = array_values(array_filter(
            array_unique($validated['groups'] ?? []),
            fn($g) => is_string($g) && $g !== '' && isset($validGroupIdSet[$g]),
        ));

        return [
            'name'             => $validated['name'],
            'sub'              => $validated['sub'] ?? null,
            'manufacturer_id'  => $validated['manufacturer_id'],
            'category'         => $validated['category'],
            'images'           => $images,
            'description_tabs' => $tabs,
            'sizes'            => $sizes,
            'filter_keys'      => $pathologyKeys,
            'groups'           => $groups,
        ];
    }

    /**
     * Normalize the flat options input. Drops empty rows, validates
     * dimensionKey references against rules.json, requires numeric
     * min/max on every measurement row.
     *
     * Returns the canonical shape:
     *   [
     *     'options' => [
     *       [
     *         'label' => 'Taille 1',
     *         'displayRange' => '19-21 cm',
     *         'measurements' => [
     *           'tour_de_cheville' => [
     *             'min' => 19, 'max' => 21, 'displayRange' => '19 cm - 21 cm'
     *           ]
     *         ]
     *       ],
     *       ...
     *     ]
     *   ]
     *
     * Returns null if nothing was filled (so DB column stays NULL).
     */
    private function normalizeAndValidateSizes(array $options): ?array
    {
        $validDimKeys = $this->collectDimensionKeys();
        $out = [];

        foreach ($options as $oi => $opt) {
            if (!is_array($opt)) continue;

            $label        = trim((string) ($opt['label']        ?? ''));
            $displayRange = trim((string) ($opt['displayRange'] ?? ''));
            $rawMeasurements = $opt['measurements'] ?? [];
            if (!is_array($rawMeasurements)) $rawMeasurements = [];

            $measurements = [];
            foreach ($rawMeasurements as $dimKey => $m) {
                if (!is_string($dimKey) || $dimKey === '') continue;
                if (!is_array($m)) continue;

                $min      = $m['min'] ?? null;
                $max      = $m['max'] ?? null;
                $mDisplay = trim((string) ($m['displayRange'] ?? ''));

                // Skip a measurement row that's entirely empty.
                if (($min === null || $min === '') && ($max === null || $max === '') && $mDisplay === '') {
                    continue;
                }

                if (!is_numeric($min) || !is_numeric($max)) {
                    abort(422, "Option #" . ($oi + 1) . " / dim '$dimKey': both min and max are required and must be numeric.");
                }
                $min = (float) $min;
                $max = (float) $max;
                if ($min > $max) {
                    abort(422, "Option #" . ($oi + 1) . " / dim '$dimKey': min ($min) cannot be greater than max ($max).");
                }
                // Unknown dimensionKey is allowed silently — admin may
                // not have added it to rules.json yet. The blade form
                // surfaces a warning via the ⚠ prefix on the dropdown.

                $measurements[$dimKey] = [
                    'min'          => $min,
                    'max'          => $max,
                    'displayRange' => $mDisplay,
                ];
            }

            // Skip a fully empty option row.
            if ($label === '' && $displayRange === '' && empty($measurements)) continue;

            if ($label === '') {
                abort(422, "Option #" . ($oi + 1) . ": label required.");
            }

            $out[] = [
                'label'        => $label,
                'displayRange' => $displayRange,
                'measurements' => $measurements,
            ];
        }

        return empty($out) ? null : ['options' => $out];
    }

    /**
     * Collect every dimensionKey declared in rules.json.
     * Used to validate sizes input — admin shouldn't reference a
     * dimension that doesn't exist in the rules tree.
     */
    private function collectDimensionKeys(): array
    {
        $keys = [];
        foreach ($this->loadFilterTree() as $node) {
            $dk = $node['dimensionKey'] ?? null;
            if (is_string($dk) && $dk !== '') {
                $keys[] = $dk;
            }
        }
        return array_values(array_unique($keys));
    }

    /**
     * Collect every child id that belongs to a measurement node (= a
     * node with a dimensionKey). These are the bucket ids; they must
     * NOT appear in the pathology filter_keys list on a product.
     */
    private function collectBucketIds(): array
    {
        $ids = [];
        foreach ($this->loadFilterTree() as $node) {
            $dk = $node['dimensionKey'] ?? null;
            if (!is_string($dk) || $dk === '') continue;
            foreach (($node['children'] ?? []) as $child) {
                if (isset($child['id']) && is_string($child['id']) && $child['id'] !== '') {
                    $ids[] = $child['id'];
                }
            }
        }
        return $ids;
    }

    /**
     * Load protocols.json. Returns the inner protocols array (the file
     * is wrapped under a top-level "protocols" key). Empty array on
     * missing or malformed file — the form panel will show a "no
     * protocols configured" message.
     */
    private function loadProtocols(): array
    {
        $path = storage_path('app/product/protocols.json');
        if (!file_exists($path)) return [];

        $raw = file_get_contents($path);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return [];
        $protocols = $decoded['protocols'] ?? [];
        return is_array($protocols) ? $protocols : [];
    }

    /**
     * Collect every filter-type group id across all protocols and
     * phases. Used to validate the admin's group selection — only
     * filter groups are assignable to a product. Info groups have
     * type "info" and are excluded.
     */
    private function collectFilterGroupIds(): array
    {
        $ids = [];
        foreach ($this->loadProtocols() as $protocol) {
            foreach (($protocol['phases'] ?? []) as $phase) {
                foreach (($phase['groups'] ?? []) as $group) {
                    if (($group['type'] ?? null) !== 'filter') continue;
                    $id = $group['id'] ?? null;
                    if (is_string($id) && $id !== '') $ids[] = $id;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    public function getRules()
    {
        $data = $this->loadFilterTree();
        return response()->json($data);
    }

    public function viewRules()
    {
        return view('products.rules.index');
    }

    public function editRules()
    {
        return view('products.rules.edit');
    }
}
