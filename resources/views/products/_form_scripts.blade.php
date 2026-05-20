<script>
$(document).ready(function () {
    // ============================================================
    // Dynamic description tabs
    // ============================================================
    var $tabsContainer = $('#tabs-container');

    // Build a fresh tab row in JS (mirrors _tab_row.blade.php structure)
    function buildTabRow(idx) {
        var html = ''
            + '<div class="tab-row" style="border:1px solid #e8e8e8;border-radius:4px;padding:12px;margin-bottom:10px;background:#fafafa;">'
            +   '<div style="display:flex;gap:10px;align-items:center;margin-bottom:8px;">'
            +     '<input type="text" name="tabs['+idx+'][title]" placeholder="Tab title" class="form-control" style="flex:1;">'
            +     '<button type="button" class="btn btn-sm btn-danger btn-remove-tab" title="Remove tab">'
            +       '<i class="voyager-trash"></i>'
            +     '</button>'
            +   '</div>'
            +   '<textarea name="tabs['+idx+'][content_markdown]" rows="6" class="form-control" placeholder="Markdown content..."></textarea>'
            + '</div>';
        return $(html);
    }

    function reindexTabs() {
        $tabsContainer.find('.tab-row').each(function (i, row) {
            $(row).find('input[name^="tabs"]').attr('name', 'tabs['+i+'][title]');
            $(row).find('textarea[name^="tabs"]').attr('name', 'tabs['+i+'][content_markdown]');
        });
    }

    $('#btn-add-tab').on('click', function () {
        var nextIdx = $tabsContainer.find('.tab-row').length;
        $tabsContainer.append(buildTabRow(nextIdx));
    });

    $tabsContainer.on('click', '.btn-remove-tab', function () {
        $(this).closest('.tab-row').remove();
        reindexTabs();
    });

    // ============================================================
    // Filter tree — port of the RN logic.ts evaluator
    // ============================================================
    var treeData = [];
    var selected = new Set();

    try {
        treeData = JSON.parse($('#filter-tree-data').text() || '[]');
    } catch (e) {
        treeData = [];
    }
    try {
        var arr = JSON.parse($('#filter-selected-data').text() || '[]');
        arr.forEach(function (k) { selected.add(k); });
    } catch (e) {}

    // Determine selected children PER NODE from the flat selected set.
    // We need this because rules reference (nodeId, childId) pairs.
    // Build it on demand; selections object maps nodeId -> [childId, ...]
    function buildSelections() {
        var sel = {};
        treeData.forEach(function (node) {
            var picked = node.children
                .filter(function (c) { return selected.has(c.id); })
                .map(function (c) { return c.id; });
            if (picked.length > 0) sel[node.id] = picked;
        });
        return sel;
    }

    function evaluateRule(rule, sel) {
        if (!rule) return true;
        if (rule.kind === 'selected') {
            return (sel[rule.nodeId] || []).indexOf(rule.childId) !== -1;
        }
        if (rule.kind === 'any_selected') {
            return (sel[rule.nodeId] || []).length > 0;
        }
        if (rule.op === 'AND') return rule.rules.every(function (r) { return evaluateRule(r, sel); });
        if (rule.op === 'OR')  return rule.rules.some(function (r) { return evaluateRule(r, sel); });
        if (rule.op === 'NOT') return !evaluateRule(rule.rule, sel);
        return false;
    }

    function isNodeVisible(node, sel) {
        if (!evaluateRule(node.visibilityRule, sel)) return false;
        if (node.visibilityRule) {
            return node.children.some(function (c) { return isChildVisible(c, sel); });
        }
        return true;
    }

    function isChildVisible(child, sel) {
        return evaluateRule(child.visibilityRule, sel);
    }

    // Render: tree rebuilds on every selection change. Visible nodes only.
    function render() {
        var $tree = $('#filter-tree');
        if (!$tree.length) return;

        var sel = buildSelections();
        var html = '';

        treeData.forEach(function (node) {
            if (!isNodeVisible(node, sel)) return;

            html += '<div class="ftree-node' + (node.visibilityRule ? ' indent' : '') + '">';
            html += '<div class="ftree-title">'
                +     escapeHtml(node.title)
                +     '<span class="ftree-meta">multi</span>'
                + '</div>';

            html += '<div class="ftree-options">';
            node.children.forEach(function (child) {
                if (!isChildVisible(child, sel)) return;
                var checked = selected.has(child.id);
                html += '<label class="ftree-pill' + (checked ? ' checked' : '') + '" data-node="' + escapeAttr(node.id) + '" data-child="' + escapeAttr(child.id) + '" data-mode="' + node.selectionType + '">';
                html +=   '<input type="checkbox" name="filter_keys[]" value="' + escapeAttr(child.id) + '"' + (checked ? ' checked' : '') + ' style="display:none;">';
                html +=   escapeHtml(child.label);
                html += '</label>';
            });
            html += '</div></div>';
        });

        $tree.html(html);
        $('#filter-count').text(selected.size);
    }

    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }
    function escapeAttr(s) { return escapeHtml(s); }

    // Cascade-prune: remove selections invalidated by other deselections.
    function pruneInvalid() {
        var changed = true;
        while (changed) {
            changed = false;
            var sel = buildSelections();
            treeData.forEach(function (node) {
                if (!isNodeVisible(node, sel)) {
                    node.children.forEach(function (c) {
                        if (selected.has(c.id)) { selected.delete(c.id); changed = true; }
                    });
                    return;
                }
                node.children.forEach(function (c) {
                    if (selected.has(c.id) && !isChildVisible(c, sel)) {
                        selected.delete(c.id);
                        changed = true;
                    }
                });
            });
        }
    }

    // Pill click handler — delegated.
    // NOTE (admin form): we ignore the node's selectionType and always treat
    // pills as multi-select. A product can be suitable for many treatment
    // paths simultaneously (e.g. both rhumato and traumato), so single-select
    // semantics from the patient-facing app don't apply here.
    $('#filter-tree').on('click', '.ftree-pill', function (e) {
        e.preventDefault();
        var childId = $(this).data('child');

        if (selected.has(childId)) {
            selected.delete(childId);
        } else {
            selected.add(childId);
        }

        pruneInvalid();
        render();
    });

    $('#filter-clear').on('click', function (e) {
        e.preventDefault();
        selected.clear();
        render();
    });

    render();

    // ============================================================
    // Dynamic sizes — nested dimensions / options / variants
    // ============================================================
    var $dimsContainer = $('#dimensions-container');

    var filterKeyOptions = [];
    try {
        filterKeyOptions = JSON.parse($('#filter-key-options-data').text() || '[]');
    } catch (e) { filterKeyOptions = []; }

    // Each filter_keys field is a multi-select. Cache the <option> markup
    // string once, so we don't rebuild it for every new row.
    function buildOptionsHtml(currentValues) {
        var current = new Set(currentValues || []);
        var html = '';
        var byGroup = {};
        filterKeyOptions.forEach(function (o) {
            if (!byGroup[o.group]) byGroup[o.group] = [];
            byGroup[o.group].push(o);
        });
        Object.keys(byGroup).forEach(function (group) {
            html += '<optgroup label="' + escapeAttr(group) + '">';
            byGroup[group].forEach(function (o) {
                var sel = current.has(o.id) ? ' selected' : '';
                html += '<option value="' + escapeAttr(o.id) + '"' + sel + '>'
                      + escapeHtml(o.label) + '</option>';
            });
            html += '</optgroup>';
        });
        return html;
    }
    function escapeHtml(s) { return String(s).replace(/[&<>]/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;'})[c]; }); }
    function escapeAttr(s) { return String(s).replace(/[&"<>]/g, function (c) { return ({'&':'&amp;','"':'&quot;','<':'&lt;','>':'&gt;'})[c]; }); }

    // -----------------------------------------------------------
    // Builders — each returns a $jQuery element with the
    // appropriate `name="sizes_dimensions[di][...]"` attrs.
    // -----------------------------------------------------------

    function buildVariantRow(di, oi, vi, variant) {
        variant = variant || {};
        var html = ''
            + '<div class="variant-row" style="background:#f4f7fb;padding:8px 10px;border-radius:5px;margin-top:6px;border-left:3px solid #93b5e8;">'
            +   '<div style="font-size:10px;color:#7d8a9c;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;font-weight:700;">'
            +     'Variant — all selected filters below must match'
            +   '</div>'
            +   '<div style="display:grid;grid-template-columns:2fr 1.4fr auto;gap:6px;align-items:center;">'
            +     '<select multiple class="form-control variant-keys" size="3" title="Hold Ctrl/Cmd to select multiple. All selected keys must match." name="sizes_dimensions['+di+'][options]['+oi+'][variants]['+vi+'][filter_keys][]">'
            +       buildOptionsHtml(variant.filter_keys || [])
            +     '</select>'
            +     '<input type="text" class="form-control variant-range" name="sizes_dimensions['+di+'][options]['+oi+'][variants]['+vi+'][range]" placeholder="Display range when this variant matches" value="' + escapeAttr(variant.range || '') + '">'
            +     '<button type="button" class="btn btn-xs btn-danger btn-remove-variant" title="Remove variant"><i class="voyager-trash"></i></button>'
            +   '</div>'
            + '</div>';
        return $(html);
    }

    function buildOptionRow(di, oi, opt) {
        opt = opt || {};
        var $row = $(''
            + '<div class="option-row" style="border:1px solid #d8dde6;border-radius:6px;padding:12px;margin-bottom:10px;background:#fcfdff;">'
            +   '<div style="display:grid;grid-template-columns:1fr 1.4fr 2fr auto;gap:8px;align-items:start;">'
            +     '<div>'
            +       '<label style="display:block;font-size:10px;color:#7d8a9c;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;font-weight:700;">Label</label>'
            +       '<input type="text" class="form-control opt-label" placeholder="S, M, 1, 2…" value="' + escapeAttr(opt.label || '') + '">'
            +     '</div>'
            +     '<div>'
            +       '<label style="display:block;font-size:10px;color:#7d8a9c;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;font-weight:700;">Display range</label>'
            +       '<input type="text" class="form-control opt-range" placeholder="14-16 cm" value="' + escapeAttr(opt.range || '') + '">'
            +     '</div>'
            +     '<div>'
            +       '<label style="display:block;font-size:10px;color:#7d8a9c;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;font-weight:700;">Required filters (ALL must match)</label>'
            +       '<select multiple class="form-control opt-keys" size="3" title="Hold Ctrl/Cmd to select multiple. ALL selected keys must be in the user filters for this option to match."></select>'
            +       '<div style="font-size:10.5px;color:#94a3b8;margin-top:2px;font-style:italic;">Leave empty if you only use variants below.</div>'
            +     '</div>'
            +     '<div style="padding-top:18px;">'
            +       '<button type="button" class="btn btn-xs btn-danger btn-remove-option" title="Remove option"><i class="voyager-trash"></i></button>'
            +     '</div>'
            +   '</div>'
            +   '<div style="margin-top:12px;padding-top:10px;border-top:1px dashed #e0e6ee;">'
            +     '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">'
            +       '<div>'
            +         '<span style="font-size:11px;color:#1d3557;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Alternative match conditions</span>'
            +         '<span style="font-size:11px;color:#7d8a9c;margin-left:6px;font-style:italic;">— option matches if <strong>ANY</strong> variant matches</span>'
            +       '</div>'
            +     '</div>'
            +     '<div class="variants-container"></div>'
            +     '<button type="button" class="btn btn-xs btn-default btn-add-variant" style="margin-top:6px;"><i class="voyager-plus"></i> Add variant</button>'
            +     '<div class="opt-mix-warning" style="display:none;margin-top:8px;padding:6px 10px;background:#fff7e6;border-left:3px solid #f0a500;font-size:11.5px;color:#8a6300;">'
            +       '<strong>Note:</strong> this option has both required filters and variants. The option matches if the variants match (checked first), or if all required filters match (checked second).'
            +     '</div>'
            +   '</div>'
            + '</div>'
        );
        $row.find('.opt-label').attr('name', 'sizes_dimensions['+di+'][options]['+oi+'][label]');
        $row.find('.opt-range').attr('name', 'sizes_dimensions['+di+'][options]['+oi+'][range]');
        $row.find('.opt-keys')
            .attr('name', 'sizes_dimensions['+di+'][options]['+oi+'][filter_keys][]')
            .html(buildOptionsHtml(opt.filter_keys || []));

        var $vCont = $row.find('.variants-container');
        (opt.variants || []).forEach(function (v, vi) {
            $vCont.append(buildVariantRow(di, oi, vi, v));
        });

        updateMixWarning($row);
        return $row;
    }

    // Show a warning under an option if it has BOTH required filters and
    // variants set — that's an unusual combination and we want admin to
    // know which one will take precedence. (Variants are checked first.)
    function updateMixWarning($row) {
        var hasReqKeys = $row.find('.opt-keys option:selected').length > 0;
        var hasVariants = $row.find('.variant-row').length > 0;
        $row.find('.opt-mix-warning').toggle(hasReqKeys && hasVariants);
    }

    function buildDimensionBlock(di, dim) {
        dim = dim || {};
        var $block = $(''
            + '<div class="dimension-block" style="border:1px solid #c3ccda;border-radius:8px;padding:14px;margin-bottom:16px;background:#f8fafd;">'
            +   '<div style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end;margin-bottom:12px;">'
            +     '<div>'
            +       '<label style="display:block;font-size:10px;color:#7d8a9c;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;font-weight:700;">Dimension name</label>'
            +       '<input type="text" class="form-control dim-name" placeholder="Tour de taille, Hauteur, Longueur…" value="' + escapeAttr(dim.name || '') + '">'
            +     '</div>'
            +     '<button type="button" class="btn btn-sm btn-danger btn-remove-dimension" title="Remove dimension"><i class="voyager-trash"></i></button>'
            +   '</div>'
            +   '<div class="options-container" style="margin-left:8px;"></div>'
            +   '<button type="button" class="btn btn-xs btn-default btn-add-option" style="margin-top:4px;"><i class="voyager-plus"></i> Add option</button>'
            + '</div>'
        );
        $block.find('.dim-name').attr('name', 'sizes_dimensions['+di+'][name]');

        var $optCont = $block.find('.options-container');
        var options = dim.options || [];
        if (options.length === 0) {
            $optCont.append(buildOptionRow(di, 0, null));
        } else {
            options.forEach(function (o, oi) {
                $optCont.append(buildOptionRow(di, oi, o));
            });
        }

        return $block;
    }

    // -----------------------------------------------------------
    // Reindex helpers — after add/remove, rewrite name attributes
    // so submitted form keeps indexes 0..N-1 contiguous.
    // -----------------------------------------------------------

    function reindexAll() {
        $dimsContainer.find('.dimension-block').each(function (di, dimEl) {
            var $dim = $(dimEl);
            $dim.find('> div > .dim-name').attr('name', 'sizes_dimensions['+di+'][name]');

            $dim.find('.option-row').each(function (oi, optEl) {
                var $opt = $(optEl);
                $opt.find('.opt-label').attr('name', 'sizes_dimensions['+di+'][options]['+oi+'][label]');
                $opt.find('.opt-range').attr('name', 'sizes_dimensions['+di+'][options]['+oi+'][range]');
                $opt.find('.opt-keys').attr('name', 'sizes_dimensions['+di+'][options]['+oi+'][filter_keys][]');

                $opt.find('.variant-row').each(function (vi, vEl) {
                    var $v = $(vEl);
                    $v.find('.variant-keys').attr('name', 'sizes_dimensions['+di+'][options]['+oi+'][variants]['+vi+'][filter_keys][]');
                    $v.find('.variant-range').attr('name', 'sizes_dimensions['+di+'][options]['+oi+'][variants]['+vi+'][range]');
                });
            });
        });
    }

    // -----------------------------------------------------------
    // Hydrate initial state
    // -----------------------------------------------------------
    var initialSizes = null;
    try { initialSizes = JSON.parse($('#sizes-initial-data').text() || 'null'); } catch (e) {}
    var initialDims = (initialSizes && Array.isArray(initialSizes.dimensions))
        ? initialSizes.dimensions
        : [];

    if (initialDims.length === 0) {
        // Start with one empty dimension so user has something to fill in
        $dimsContainer.append(buildDimensionBlock(0, null));
    } else {
        initialDims.forEach(function (d, di) {
            $dimsContainer.append(buildDimensionBlock(di, d));
        });
    }

    // -----------------------------------------------------------
    // Event handlers
    // -----------------------------------------------------------

    $('#btn-add-dimension').on('click', function () {
        var di = $dimsContainer.find('.dimension-block').length;
        $dimsContainer.append(buildDimensionBlock(di, null));
    });

    $dimsContainer.on('click', '.btn-remove-dimension', function () {
        $(this).closest('.dimension-block').remove();
        reindexAll();
    });

    $dimsContainer.on('click', '.btn-add-option', function () {
        var $optCont = $(this).siblings('.options-container');
        var $dim = $(this).closest('.dimension-block');
        var di = $dimsContainer.find('.dimension-block').index($dim);
        var oi = $optCont.find('.option-row').length;
        $optCont.append(buildOptionRow(di, oi, null));
    });

    $dimsContainer.on('click', '.btn-remove-option', function () {
        $(this).closest('.option-row').remove();
        reindexAll();
    });

    $dimsContainer.on('click', '.btn-add-variant', function () {
        var $vCont = $(this).siblings('.variants-container');
        var $opt = $(this).closest('.option-row');
        var $dim = $opt.closest('.dimension-block');
        var di = $dimsContainer.find('.dimension-block').index($dim);
        var oi = $dim.find('.option-row').index($opt);
        var vi = $vCont.find('.variant-row').length;
        $vCont.append(buildVariantRow(di, oi, vi, null));
        updateMixWarning($opt);
    });

    $dimsContainer.on('click', '.btn-remove-variant', function () {
        var $opt = $(this).closest('.option-row');
        $(this).closest('.variant-row').remove();
        reindexAll();
        updateMixWarning($opt);
    });

    // Update warning when admin toggles selections in the required-filters multi-select
    $dimsContainer.on('change', '.opt-keys', function () {
        updateMixWarning($(this).closest('.option-row'));
    });
});
</script>