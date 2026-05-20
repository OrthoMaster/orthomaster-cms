{{--
    Shared product form partial.
    Required vars:
      - $product       (Product, may be unsaved instance)
      - $manufacturers (Collection of Manufacturer)
      - $filterTree    (array decoded from rules.json)
      - $selectedKeys  (array of filter_key strings already on the product)
    Required wrapping <form> around this include.
--}}

@php
    /** @var \App\Models\Product $product */
    $images = old('image_urls', is_array($product->images ?? null) ? implode("\n", $product->images) : '');
    $tabs   = old('tabs',       $product->description_tabs ?? []);
    $sizes  = old('sizes',      $product->sizes ?? []);
    $name   = old('name',       $product->name ?? '');
    $mfgId  = old('manufacturer_id', $product->manufacturer_id ?? '');
    $cat    = old('category',   $product->category ?? '');
    $sel    = old('filter_keys', $selectedKeys ?? []);
    if (!is_array($sel)) $sel = [];
    if (!is_array($sizes)) $sizes = [];
@endphp

@csrf

<div class="panel panel-bordered">
    <div class="panel-heading">
        <h3 class="panel-title">Basic info</h3>
    </div>
    <div class="panel-body">

        <div class="form-group @error('name') has-error @enderror">
            <label>Product name <span class="text-danger">*</span></label>
            <input type="text" name="name" value="{{ $name }}" class="form-control" required>
            @error('name')<small class="help-block">{{ $message }}</small>@enderror
        </div>

        <div class="form-group @error('manufacturer_id') has-error @enderror">
            <label>Manufacturer <span class="text-danger">*</span></label>
            <select name="manufacturer_id" class="form-control" required>
                <option value="">— Select manufacturer —</option>
                @foreach ($manufacturers as $m)
                    <option value="{{ $m->id }}" @selected((string)$mfgId === (string)$m->id)>
                        {{ $m->name }}
                    </option>
                @endforeach
            </select>
            @error('manufacturer_id')<small class="help-block">{{ $message }}</small>@enderror
        </div>

        <div class="form-group @error('category') has-error @enderror">
            <label>Category <span class="text-danger">*</span></label>
            <input type="text" name="category" value="{{ $cat }}" class="form-control"
                   placeholder="e.g. Orthèse de genou" required>
            @error('category')<small class="help-block">{{ $message }}</small>@enderror
        </div>
    </div>
</div>

<div class="panel panel-bordered">
    <div class="panel-heading">
        <h3 class="panel-title">Images</h3>
        <p class="text-muted" style="margin: 4px 0 0; font-size: 12px;">
            Paste S3 image URLs, one per line. Order matters — first URL is the cover.
        </p>
    </div>
    <div class="panel-body">
        <textarea name="image_urls" rows="5" class="form-control"
                  placeholder="https://your-bucket.s3.amazonaws.com/image-1.jpg&#10;https://your-bucket.s3.amazonaws.com/image-2.jpg">{{ $images }}</textarea>
    </div>
</div>

<div class="panel panel-bordered">
    <div class="panel-heading">
        <h3 class="panel-title">Description tabs</h3>
        <p class="text-muted" style="margin: 4px 0 0; font-size: 12px;">
            Each tab has a title (shown to user) and Markdown content. Empty rows are dropped on save.
        </p>
    </div>
    <div class="panel-body">
        <div id="tabs-container">
            @forelse ($tabs as $idx => $tab)
                @include('products._tab_row', ['idx' => $idx, 'tab' => $tab])
            @empty
                @include('products._tab_row', ['idx' => 0, 'tab' => ['title' => '', 'content_markdown' => '']])
            @endforelse
        </div>
        <button type="button" class="btn btn-default" id="btn-add-tab">
            <i class="voyager-plus"></i> Add tab
        </button>
    </div>
</div>

<div class="panel panel-bordered">
    <div class="panel-heading">
        <h3 class="panel-title">Sizes</h3>
    </div>
    <div class="panel-body">
        {{-- Explainer box — teaches the AND/OR mental model up front --}}
        <div style="background:#f0f6ff;border:1px solid #c5dafa;border-radius:6px;padding:12px 14px;margin-bottom:18px;font-size:12px;line-height:18px;color:#374a63;">
            <div style="font-weight:700;margin-bottom:6px;color:#1d3557;">
                <i class="voyager-info-circled"></i> How sizes match user filters
            </div>
            <div style="margin-bottom:6px;">
                Each <strong>dimension</strong> (Tour de taille, Hauteur…) is rendered as its own list in the app. Inside a dimension, each <strong>option</strong> is one selectable size (S, M, L, 1, 2, …).
            </div>
            <div style="margin-bottom:6px;">
                <strong>Required filters</strong> on an option = <em>ALL</em> listed keys must be in the user's selection. Use this when the size depends on a single combination of conditions.
                <br>
                <span style="color:#5c6b7e;font-style:italic;">Example: option "M" with required filters <code>[taille-m, homme]</code> → matches only when user has selected both Tour de taille = M <em>and</em> Type = Homme.</span>
            </div>
            <div>
                <strong>Alternative match conditions (variants)</strong> = <em>ANY</em> variant can match. Each variant is its own AND-group. Use this when a size can be reached through multiple unrelated paths.
                <br>
                <span style="color:#5c6b7e;font-style:italic;">Example: option "26cm" with no required filters and two variants:</span>
                <ul style="margin:4px 0 0 18px;padding:0;color:#5c6b7e;font-style:italic;">
                    <li>Variant 1: <code>[hauteur-xl]</code> — matches when Hauteur &gt; 185 cm</li>
                    <li>Variant 2: <code>[hauteur-l, homme]</code> — matches when Hauteur 175-185 cm <em>and</em> Type = Homme</li>
                </ul>
            </div>
        </div>

        <div id="dimensions-container">
            {{-- Hydrated by JS from #sizes-initial-data --}}
        </div>
        <button type="button" class="btn btn-default" id="btn-add-dimension">
            <i class="voyager-plus"></i> Add dimension
        </button>
    </div>
</div>

<div class="panel panel-bordered">
    <div class="panel-heading">
        <h3 class="panel-title">Pathology filters</h3>
        <p class="text-muted" style="margin: 4px 0 0; font-size: 12px;">
            Tick the options this product is suitable for. Children appear when their parent option is checked.
        </p>
    </div>
    <div class="panel-body">
        @if (empty($filterTree))
            <p class="text-warning">
                <i class="voyager-warning"></i>
                No filter tree found. Make sure <code>storage/product/rules.json</code> exists and is valid JSON.
            </p>
        @else
            <div id="filter-tree"></div>
            <p class="text-muted" style="margin-top: 10px;">
                <span id="filter-count">{{ count($sel) }}</span> selection(s).
                <a href="#" id="filter-clear" style="margin-left: 8px;">Clear all</a>
            </p>
        @endif
    </div>
</div>

<div class="form-group" style="margin-top: 18px;">
    <button type="submit" class="btn btn-primary">
        <i class="voyager-check"></i>
        {{ $product->exists ? 'Update product' : 'Create product' }}
    </button>
    <a href="{{ route('products.index') }}" class="btn btn-default">Cancel</a>
</div>

{{-- Pass server data to JS without inline-script-injection issues --}}
<script type="application/json" id="filter-tree-data">{!! json_encode($filterTree, JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/json" id="filter-selected-data">{!! json_encode(array_values($sel), JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/json" id="filter-key-options-data">{!! json_encode($filterKeyOptions ?? [], JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/json" id="sizes-initial-data">{!! json_encode($sizes ?? null, JSON_UNESCAPED_UNICODE) !!}</script>