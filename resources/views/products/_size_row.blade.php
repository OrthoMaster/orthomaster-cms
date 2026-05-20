{{--
    One size row.
    Required vars:
      - $idx  (int)   — used to build the name attribute
      - $size (array) — { label, range, filter_key }

    The filter_key dropdown is populated client-side from
    #measurement-options-data so we don't have to repeat the option list
    in markup for every row (and so dynamically-added rows pick it up).
--}}

@php
    $label = $size['label']      ?? '';
    $range = $size['range']      ?? '';
    $fk    = $size['filter_key'] ?? '';
@endphp

<div class="size-row" style="display: grid; grid-template-columns: 1fr 1.2fr 2fr auto; gap: 8px; margin-bottom: 8px; align-items: center;">
    <input type="text"
           name="sizes[{{ $idx }}][label]"
           value="{{ $label }}"
           class="form-control size-label"
           placeholder="Label (e.g. S, M, L, 1, 2)">

    <input type="text"
           name="sizes[{{ $idx }}][range]"
           value="{{ $range }}"
           class="form-control size-range"
           placeholder="Range (e.g. 14-16 cm)">

    <select name="sizes[{{ $idx }}][filter_key]"
            class="form-control size-filter-key"
            data-current="{{ $fk }}">
        <option value="">— Select filter bucket —</option>
        {{-- Options injected by JS from #measurement-options-data --}}
    </select>

    <button type="button" class="btn btn-danger btn-xs btn-remove-size" title="Remove">
        <i class="voyager-trash"></i>
    </button>
</div>