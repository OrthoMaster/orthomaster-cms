@php
    /** @var \App\Models\Product $product */
    $images = old('image_urls', is_array($product->images ?? null) ? implode("\n", $product->images) : '');
    $tabs = old('tabs', $product->description_tabs ?? []);
    $name = old('name', $product->name ?? '');
    $mfgId = old('manufacturer_id', $product->manufacturer_id ?? '');
    $cat = old('category', $product->category ?? '');
    $sel = old('filter_keys', $selectedKeys ?? []);
    if (!is_array($sel)) {
        $sel = [];
    }
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
            @error('name')
                <small class="help-block">{{ $message }}</small>
            @enderror
        </div>

        <div class="form-group @error('manufacturer_id') has-error @enderror">
            <label>Manufacturer <span class="text-danger">*</span></label>
            <select name="manufacturer_id" class="form-control" required>
                <option value="">— Select manufacturer —</option>
                @foreach ($manufacturers as $m)
                    <option value="{{ $m->id }}" @selected((string) $mfgId === (string) $m->id)>
                        {{ $m->name }}
                    </option>
                @endforeach
            </select>
            @error('manufacturer_id')
                <small class="help-block">{{ $message }}</small>
            @enderror
        </div>

        <div class="form-group @error('category') has-error @enderror">
            <label>Category <span class="text-danger">*</span></label>
            <input type="text" name="category" value="{{ $cat }}" class="form-control"
                placeholder="e.g. Orthèse de genou" required>
            @error('category')
                <small class="help-block">{{ $message }}</small>
            @enderror
        </div>
    </div>
</div>

<div class="panel panel-bordered">
    <div class="panel-heading">
        <h3 class="panel-title">Images
            <small class="text-muted" style="margin: 4px 0 0; font-size: 12px;">
                Paste S3 image URLs, one per line. Order matters — first URL is the cover.
            </small>
        </h3>
    </div>
    <div class="panel-body">
        <textarea name="image_urls" rows="5" class="form-control"
            placeholder="https://orthomaster.s3.amazonaws.com/image-1.jpg&#10;https://orthomaster.s3.amazonaws.com/image-2.jpg">{{ $images }}</textarea>
    </div>
</div>

<div class="panel panel-bordered">
    <div class="panel-heading">
        <h3 class="panel-title">Description tabs
            <small class="text-muted" style="margin: 4px 0 0; font-size: 12px;">
                Each tab has a title (shown to user) and Markdown content. Empty rows are dropped on save.
            </small>
        </h3>
    </div>
    <div class="panel-body">
        <div id="tabs-container">
            @forelse ($tabs as $idx => $tab)
                @include('products._tab_row', ['idx' => $idx, 'tab' => $tab])
            @empty
                @include('products._tab_row', [
                    'idx' => 0,
                    'tab' => ['title' => '', 'content_markdown' => ''],
                ])
            @endforelse
        </div>
        <button type="button" class="btn btn-default" id="btn-add-tab">
            <i class="voyager-plus"></i> Add tab
        </button>
    </div>
</div>

<div class="panel panel-bordered">
    <div class="panel-heading">
        <h3 class="panel-title">Pathology filters
            <small class="text-muted" style="margin: 4px 0 0; font-size: 12px;">
                Tick the options this product is suitable for. Children appear when their parent option is checked.
            </small>
        </h3>
    </div>
    <div class="panel-body">
        @if (empty($filterTree))
            <p class="text-warning">
                <i class="voyager-warning"></i>
                No filter tree found. Make sure <code>storage/app/product/rules.json</code> exists and is valid JSON.
            </p>
        @else
            <div id="filter-tree"></div>
            <p class="text-muted" style="margin-top: 10px;">
                <span id="filter-count">{{ count($sel) }}</span> selection(s).
                <a href="#" id="filter-clear" style="margin-left: 8px; color: red">Clear all filters</a>
            </p>
        @endif
    </div>
</div>

<div class="form-group" style="margin-top: 18px;">
    @if ($product->exists)
        <button type="submit" class="btn btn-warning">
            <i class="voyager-edit"></i> Modify
        </button>
    @else
        <button type="submit" class="btn btn-primary">
            <i class="voyager-check"></i> Add new
        </button>
    @endif
    <a href="{{ route('products.index') }}" class="btn btn-default">Cancel</a>
</div>

{{-- Pass server data to JS without inline-script-injection issues --}}
<script type="application/json" id="filter-tree-data">
    {!! json_encode($filterTree, JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/json" id="filter-selected-data">
    {!! json_encode(array_values($sel), JSON_UNESCAPED_UNICODE) !!}
</script>
