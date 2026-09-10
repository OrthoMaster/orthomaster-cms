{{--
    Shared product form partial.
    Required vars:
      - $product               (Product, may be unsaved instance)
      - $manufacturers         (Collection of Manufacturer)
      - $filterTree            (array decoded from rules.json)
      - $selectedKeys          (array of pathology filter_key strings)
      - $measurementDimensions (array of {key, title} for size form dropdown)
      - $protocolsTree         (array decoded from protocols.json — list of protocols)
      - $selectedGroups        (array of group id strings already on this product)
    Required wrapping <form> around this include.
--}}

@php
    /** @var \App\Models\Product $product */
    $images = old('image_urls', is_array($product->images ?? null) ? implode("\n", $product->images) : '');
    $tabs   = old('tabs',       $product->description_tabs ?? []);
    $sizes  = old('sizes',      $product->sizes ?? []);
    $name   = old('name',       $product->name ?? '');
    $sub    = old('sub',        $product->sub ?? '');
    $mfgId  = old('manufacturer_id', $product->manufacturer_id ?? '');
    $cat    = old('category',   $product->category ?? '');
    $sel    = old('filter_keys', $selectedKeys ?? []);
    if (!is_array($sel)) $sel = [];
    if (!is_array($sizes)) $sizes = [];

    $selGroups = old('groups', $selectedGroups ?? []);
    if (!is_array($selGroups)) $selGroups = [];
@endphp

@csrf

<div class="panel panel-bordered">
    <div class="panel-heading">
        <h3 class="panel-title">Pathology filters</h3>
        <p class="text-muted" style="margin: 4px 0 0; font-size: 12px;">
            Tick the options this product is suitable for. Children appear when their parent option is checked.
            Measurement buckets (Tour de poignet, Hauteur…) are NOT shown here — they're matched automatically via the product's size measurements.
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

            <div style="margin-top:12px;padding-top:12px;border-top:1px dashed #e0e6ee;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <label style="font-size:11px;color:#7d8a9c;text-transform:uppercase;letter-spacing:.5px;font-weight:700;margin:0;">
                        Export — collez dans la colonne <code>keys</code> de l'onglet Groupes
                    </label>
                    <button type="button" class="btn btn-xs btn-info" id="filter-copy">
                        <i class="voyager-file-text"></i> Copy
                    </button>
                </div>
                <textarea id="filter-export" readonly rows="3" class="form-control"
                          style="font-family:monospace;font-size:11.5px;background:#f7fafc;"></textarea>
                <small class="text-muted" style="font-size:11px;">
                    Copiez la liste <strong>entière</strong> — les options parentes sont nécessaires,
                    sinon les pathologies seront rejetées à l'import.
                </small>
            </div>
        @endif
    </div>
</div>

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

        <div class="form-group @error('sub') has-error @enderror">
            <label>Subtitle</label>
            <input type="text" name="sub" value="{{ $sub }}" class="form-control"
                maxlength="255" placeholder="e.g. Ligamentaire">
            @error('sub')<small class="help-block">{{ $message }}</small>@enderror
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

        @if ($product)
        <div>
            <label>Source</label>
            <a href="{{ $product->source }}" target="_blank">{{ $product->source }}</a>
        </div>
        @endif
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
                @include('products._tab_row', ['idx' => 0, 'tab' => ['title' => '', 'content' => '']])
            @endforelse
        </div>
        <button type="button" class="btn btn-default" id="btn-add-tab">
            <i class="voyager-plus"></i> Add tab
        </button>
    </div>
</div>

<div class="panel panel-bordered">
    <div class="panel-heading">
        <h3 class="panel-title">
            Sizes
            <button type="button" class="btn btn-xs btn-info" id="btn-parse-markdown"
                    style="margin-left:12px;font-weight:600;">
                <i class="voyager-magic"></i> Parse from markdown
            </button>
        </h3>
    </div>
    <div class="panel-body">
        {{-- Explainer box — schema mental model --}}
        <div style="background:#f0f6ff;border:1px solid #c5dafa;border-radius:6px;padding:12px 14px;margin-bottom:18px;font-size:12px;line-height:18px;color:#374a63;">
            <div style="font-weight:700;margin-bottom:6px;color:#1d3557;">
                <i class="voyager-info-circled"></i> How sizes match user filters
            </div>
            <div style="margin-bottom:6px;">
                Each <strong>option</strong> is one size variant of this product (Taille 1, S, M, …). Each option declares numeric <strong>measurements</strong> — the actual ranges that taille covers (e.g. tour de cheville 19-21 cm).
            </div>
            <div>
                When a user picks a measurement bucket in the app (e.g. tour de cheville <em>22-25 cm</em>), the app matches the product if at least one option's measurement <em>overlaps</em> that bucket. Below, pick the right <em>dimension</em> for each measurement so the overlap can be computed.
            </div>
        </div>

        <div id="options-container">
            {{-- Hydrated by JS from #sizes-initial-data --}}
        </div>
        <button type="button" class="btn btn-default" id="btn-add-option">
            <i class="voyager-plus"></i> Add option
        </button>
    </div>
</div>

<div class="panel panel-bordered">
    <div class="panel-heading">
        <h3 class="panel-title">Groupes de protocole</h3>
        <p class="text-muted" style="margin: 4px 0 0; font-size: 12px;">
            Tick the protocol groups this product belongs to. Grouped by protocol &gt; phase. Only filter-type groups
            are shown — info groups (text-only hints) aren't selectable.
        </p>
    </div>
    <div class="panel-body">
        @if (empty($protocolsTree))
            <p class="text-warning" style="margin:0;">
                <i class="voyager-warning"></i>
                No protocols configured yet. Make sure <code>storage/product/protocols.json</code> exists and contains at least one protocol.
            </p>
        @else
            <div id="protocol-groups-tree"></div>
            <p class="text-muted" style="margin-top: 10px;">
                <span id="group-count">{{ count($selGroups) }}</span> group(s) selected.
                <a href="#" id="group-clear" style="margin-left: 8px;">Clear all</a>
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

{{-- Markdown paste modal — hidden until "Parse from markdown" is clicked.
     Plain CSS modal so we don't depend on Bootstrap JS being loaded. --}}
<div id="markdown-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.45);">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:8px;padding:22px;width:680px;max-width:92vw;max-height:90vh;overflow:auto;box-shadow:0 12px 40px rgba(0,0,0,0.25);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <h4 style="margin:0;font-weight:600;">Parse sizes from markdown</h4>
            <button type="button" class="btn btn-xs btn-default" id="md-close" title="Close">✕</button>
        </div>
        <p class="text-muted" style="font-size:12px;line-height:18px;margin-bottom:10px;">
            Paste a markdown table where the first column is the size label and other columns are measurements.
            Headers are normalized to <code>dimensionKey</code> (lowercase, no accents, snake_case) and matched against your rules.
        </p>
        <textarea id="md-input" rows="10" class="form-control" style="font-family:monospace;font-size:12px;"
                  placeholder="| Taille | Tour de cheville |&#10;| --- | --- |&#10;| Taille 1 | 19 cm - 21 cm |&#10;| Taille 2 | 21 cm - 23 cm |"></textarea>
        <div id="md-warnings" style="margin-top:10px;font-size:12px;line-height:18px;color:#8a6300;"></div>
        <div style="display:flex;gap:8px;margin-top:14px;justify-content:flex-end;">
            <button type="button" class="btn btn-default" id="md-cancel">Cancel</button>
            <button type="button" class="btn btn-info"    id="md-append">Append</button>
            <button type="button" class="btn btn-primary" id="md-replace">Parse and replace</button>
        </div>
    </div>
</div>

{{-- Pass server data to JS without inline-script-injection issues --}}
<script type="application/json" id="filter-tree-data">{!! json_encode($filterTree, JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/json" id="filter-selected-data">{!! json_encode(array_values($sel), JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/json" id="measurement-dimensions-data">{!! json_encode($measurementDimensions ?? [], JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/json" id="sizes-initial-data">{!! json_encode($sizes ?? null, JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/json" id="protocols-tree-data">{!! json_encode($protocolsTree ?? [], JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/json" id="groups-selected-data">{!! json_encode(array_values($selGroups), JSON_UNESCAPED_UNICODE) !!}</script>