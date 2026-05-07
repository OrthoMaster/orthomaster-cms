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
});
</script>