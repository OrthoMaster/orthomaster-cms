{{-- A single description tab row. Used both in initial render and as JS template. --}}
<div class="tab-row"
    style="border: 1px solid #e8e8e8; border-radius: 4px; padding: 12px; margin-bottom: 10px; background: #fafafa;">
    <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 8px;">
        <input type="text" name="tabs[{{ $idx }}][title]" value="{{ $tab['title'] ?? '' }}"
            placeholder="Tab title (e.g Details)" class="form-control" style="flex: 1;">
        <button type="button" class="btn btn-sm btn-danger btn-remove-tab" title="Remove tab">
            <i class="voyager-trash"></i>
        </button>
    </div>
    <textarea name="tabs[{{ $idx }}][content]" rows="6" class="form-control"
        placeholder="Markdown content...">{{ $tab['content'] ?? '' }}</textarea>
</div>
