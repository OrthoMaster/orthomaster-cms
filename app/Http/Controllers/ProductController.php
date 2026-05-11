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
            ]);
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
     * PUT/PATCH admin/products/{product}
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
    private function formData(Product $product)
    {
        return [
            'product'       => $product,
            'manufacturers' => Manufacturer::orderBy('name')->get(),
            'filterTree'    => $this->loadFilterTree(),
            'selectedKeys'  => $product->exists
                ? $product->filters->pluck('filter_key')->all()
                : [],
        ];
    }

    /**
     * Load the filter rules JSON from storage/product/rules.json.
     * Returns [] on failure so the view can still render (with a warning).
     */
    private function loadFilterTree()
    {
        $path = storage_path('app/product/rules.json');
        if (!file_exists($path)) return [];

        $raw = file_get_contents($path);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Validate + normalize the form payload. Returns the cleaned array
     * ready to assign to the model.
     */
    private function validateInput(Request $request)
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
        ]);

        // Parse image URLs from textarea (one per line, trim, drop empty)
        $images = [];
        if (!empty($validated['image_urls'])) {
            $images = collect(preg_split('/\r?\n/', $validated['image_urls']))
                ->map(fn($l) => trim($l))
                ->filter(fn($l) => $l !== '')
                ->values()
                ->all();
        }

        // Filter out empty tabs (rows where both title & content blank)
        $tabs = collect($validated['tabs'] ?? [])
            ->map(fn($t) => [
                'title'            => trim($t['title'] ?? ''),
                'content_markdown' => $t['content_markdown'] ?? '',
            ])
            ->filter(fn($t) => $t['title'] !== '' || $t['content_markdown'] !== '')
            ->values()
            ->all();

        return [
            'name'             => $validated['name'],
            'manufacturer_id'  => $validated['manufacturer_id'],
            'category'         => $validated['category'],
            'images'           => $images,
            'description_tabs' => $tabs,
            'filter_keys'      => array_values(array_unique($validated['filter_keys'] ?? [])),
        ];
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
