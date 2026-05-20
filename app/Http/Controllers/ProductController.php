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
    public function index()
    {
        $products = Product::with('manufacturer')->orderBy('name')->paginate(20);
        return view('products.index', compact('products'));
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
                'manufacturer_id'  => $data['manufacturer_id'],
                'category'         => $data['category'],
                'images'           => $data['images'],
                'description_tabs' => $data['description_tabs'],
                'sizes'            => $data['sizes'],
            ]);
            // Merge user-picked filter keys with the keys derived from
            // size rows. A product gets the size filter_key as a filter
            // tag automatically — admin doesn't have to also tick the
            // pill in the filter tree.
            $merged = array_values(array_unique(array_merge(
                $data['filter_keys'],
                $product->sizeFilterKeys(),
            )));
            $product->syncFilters($merged);
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
                'manufacturer_id'  => $data['manufacturer_id'],
                'category'         => $data['category'],
                'images'           => $data['images'],
                'description_tabs' => $data['description_tabs'],
                'sizes'            => $data['sizes'],
            ]);
            $merged = array_values(array_unique(array_merge(
                $data['filter_keys'],
                $product->sizeFilterKeys(),
            )));
            $product->syncFilters($merged);
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
        return redirect()
            ->route('products.index')
            ->with('message', 'Product deleted.')
            ->with('alert-type', 'success');
    }
 
    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Build the bag of variables every form view needs:
     *   - product       (model instance, possibly empty for create)
     *   - manufacturers (for the select)
     *   - filterTree    (decoded rules.json)
     *   - selectedKeys  (filter keys already on this product)
     */
    private function formData(Product $product): array
    {
        $tree = $this->loadFilterTree();
        return [
            'product'         => $product,
            'manufacturers'   => Manufacturer::orderBy('name')->get(),
            'filterTree'      => $tree,
            'selectedKeys'    => $product->exists
                ? $product->filters->pluck('filter_key')->all()
                : [],
            // Flat list of every filter option in the tree, grouped by node
            // title. Used by the sizes form to populate filter_key
            // multi-selects. Includes Type, Age, Activité (variant
            // conditions) AND mesure_* nodes (the typical primary axis).
            'filterKeyOptions' => $this->extractFilterKeyOptions($tree),
        ];
    }

    /**
     * Flatten the entire filter tree into a list of options the size form
     * can use. One entry per child, grouped by parent node title.
     *
     * Returns:
     *   [
     *     { group: "Tour de poignet", id: "poignet-s", label: "Petit (< 17 cm)" },
     *     ...
     *     { group: "Type", id: "homme", label: "Homme" },
     *     ...
     *   ]
     */
    private function extractFilterKeyOptions(array $tree): array
    {
        $out = [];
        foreach ($tree as $node) {
            $groupTitle = $node['title'] ?? ($node['id'] ?? '');
            foreach (($node['children'] ?? []) as $child) {
                $id = $child['id'] ?? '';
                if (!is_string($id) || $id === '') continue;
                // Skip pure indicator children (no leaf value of their own).
                // Heuristic: skip when the child id matches another node id
                // — those are pointers, not values (e.g. patient → "type"
                // points at the Type node).
                $isPointer = false;
                foreach ($tree as $n) {
                    if (($n['id'] ?? '') === $id) {
                        $isPointer = true;
                        break;
                    }
                }
                if ($isPointer) continue;

                $out[] = [
                    'group' => $groupTitle,
                    'id'    => $id,
                    'label' => $child['label'] ?? $id,
                ];
            }
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
     */
    private function validateInput(Request $request): array
    {
        $validated = $request->validate([
            'name'                            => ['required', 'string', 'max:255'],
            'manufacturer_id'                 => ['required', 'integer', 'exists:manufacturers,id'],
            'category'                        => ['required', 'string', 'max:255'],
            'image_urls'                      => ['nullable', 'string'],
            'tabs'                            => ['nullable', 'array'],
            'tabs.*.title'                    => ['nullable', 'string', 'max:255'],
            'tabs.*.content_markdown'         => ['nullable', 'string'],
            'filter_keys'                     => ['nullable', 'array'],
            'filter_keys.*'                   => ['string', 'max:64'],
            // Nested sizes structure. Each dimension is a list of options;
            // each option has either flat filter_keys OR variants (OR'd).
            'sizes_dimensions'                                  => ['nullable', 'array'],
            'sizes_dimensions.*.name'                           => ['nullable', 'string', 'max:64'],
            'sizes_dimensions.*.options'                        => ['nullable', 'array'],
            'sizes_dimensions.*.options.*.label'                => ['nullable', 'string', 'max:64'],
            'sizes_dimensions.*.options.*.range'                => ['nullable', 'string', 'max:64'],
            'sizes_dimensions.*.options.*.filter_keys'          => ['nullable', 'array'],
            'sizes_dimensions.*.options.*.filter_keys.*'        => ['string', 'max:64'],
            'sizes_dimensions.*.options.*.variants'             => ['nullable', 'array'],
            'sizes_dimensions.*.options.*.variants.*.range'     => ['nullable', 'string', 'max:64'],
            'sizes_dimensions.*.options.*.variants.*.filter_keys' => ['nullable', 'array'],
            'sizes_dimensions.*.options.*.variants.*.filter_keys.*' => ['string', 'max:64'],
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
                'title'            => trim($t['title'] ?? ''),
                'content_markdown' => $t['content_markdown'] ?? '',
            ])
            ->filter(fn($t) => $t['title'] !== '' || $t['content_markdown'] !== '')
            ->values()
            ->all();

        $sizes = $this->normalizeAndValidateSizes($validated['sizes_dimensions'] ?? []);

        return [
            'name'             => $validated['name'],
            'manufacturer_id'  => $validated['manufacturer_id'],
            'category'         => $validated['category'],
            'images'           => $images,
            'description_tabs' => $tabs,
            'sizes'            => $sizes,
            'filter_keys'      => array_values(array_unique($validated['filter_keys'] ?? [])),
        ];
    }

    /**
     * Normalize the dimensions/options/variants input. Drops empty rows,
     * validates filter_keys against rules.json, and asserts that each
     * non-empty option has either flat filter_keys OR variants (not both
     * empty).
     *
     * Returns the canonical shape:
     *   [
     *     'dimensions' => [
     *       [
     *         'name' => '...',
     *         'options' => [
     *           [ 'label'=>'M', 'range'=>'', 'filter_keys'=>['x','y'] ],
     *           [ 'label'=>'M', 'range'=>'', 'variants'=>[
     *               [ 'filter_keys'=>['x','y'], 'range'=>'...' ]
     *           ]],
     *         ]
     *       ]
     *     ]
     *   ]
     * Returns null if nothing was filled (so DB column stays NULL).
     */
    private function normalizeAndValidateSizes(array $dimensions): ?array
    {
        $validKeys = $this->allValidFilterKeys();
        $out = [];

        foreach ($dimensions as $di => $dim) {
            $name = trim($dim['name'] ?? '');
            $rawOptions = $dim['options'] ?? [];
            if (!is_array($rawOptions)) $rawOptions = [];

            $options = [];
            foreach ($rawOptions as $oi => $opt) {
                $label = trim($opt['label'] ?? '');
                $range = trim($opt['range'] ?? '');
                $flatKeys = array_values(array_filter(
                    array_map('trim', $opt['filter_keys'] ?? []),
                    fn($k) => is_string($k) && $k !== ''
                ));
                $rawVariants = $opt['variants'] ?? [];
                if (!is_array($rawVariants)) $rawVariants = [];

                // Normalize variants
                $variants = [];
                foreach ($rawVariants as $vi => $v) {
                    $vRange = trim($v['range'] ?? '');
                    $vKeys = array_values(array_filter(
                        array_map('trim', $v['filter_keys'] ?? []),
                        fn($k) => is_string($k) && $k !== ''
                    ));
                    // Skip a variant that's fully empty
                    if ($vRange === '' && empty($vKeys)) continue;
                    if (empty($vKeys)) {
                        abort(422, "Dimension #" . ($di + 1) . " / Option #" . ($oi + 1)
                            . " / Variant #" . ($vi + 1) . ": at least one filter_key required.");
                    }
                    foreach ($vKeys as $k) {
                        if (!in_array($k, $validKeys, true)) {
                            abort(422, "Dimension #" . ($di + 1) . " / Option #" . ($oi + 1)
                                . " / Variant #" . ($vi + 1) . ": unknown filter_key '{$k}'.");
                        }
                    }
                    $variants[] = ['filter_keys' => $vKeys, 'range' => $vRange];
                }

                // Skip a fully empty option row
                if ($label === '' && $range === '' && empty($flatKeys) && empty($variants)) continue;

                if ($label === '') {
                    abort(422, "Dimension #" . ($di + 1) . " / Option #" . ($oi + 1) . ": label required.");
                }

                // Validate flat filter_keys
                foreach ($flatKeys as $k) {
                    if (!in_array($k, $validKeys, true)) {
                        abort(422, "Dimension #" . ($di + 1) . " / Option #" . ($oi + 1)
                            . ": unknown filter_key '{$k}'.");
                    }
                }

                // Option must have at least one match path: flat keys OR variants
                if (empty($flatKeys) && empty($variants)) {
                    abort(422, "Dimension #" . ($di + 1) . " / Option #" . ($oi + 1)
                        . ": either filter_keys or variants is required.");
                }

                $option = ['label' => $label, 'range' => $range];
                if (!empty($flatKeys)) $option['filter_keys'] = $flatKeys;
                if (!empty($variants)) $option['variants']    = $variants;
                $options[] = $option;
            }

            // Skip an empty dimension
            if ($name === '' && empty($options)) continue;
            if ($name === '') {
                abort(422, "Dimension #" . ($di + 1) . ": name required.");
            }
            if (empty($options)) continue;     // dimension named but no options → drop silently

            $out[] = ['name' => $name, 'options' => $options];
        }

        return empty($out) ? null : ['dimensions' => $out];
    }

    /**
     * Collect every valid filter_key (=child id) from rules.json.
     * Used to validate that size.filter_key references an existing bucket.
     */
    private function allValidFilterKeys(): array
    {
        $keys = [];
        foreach ($this->loadFilterTree() as $node) {
            foreach (($node['children'] ?? []) as $child) {
                if (isset($child['id']) && is_string($child['id'])) {
                    $keys[] = $child['id'];
                }
            }
        }
        return $keys;
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
