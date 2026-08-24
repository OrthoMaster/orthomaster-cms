<script>
$(document).ready(function () {
    // ============================================================
    // Dynamic description tabs
    // ============================================================
    var $tabsContainer = $('#tabs-container');

    function buildTabRow(idx) {
        var html = ''
            + '<div class="tab-row" style="border:1px solid #e8e8e8;border-radius:4px;padding:12px;margin-bottom:10px;background:#fafafa;">'
            +   '<div style="display:flex;gap:10px;align-items:center;margin-bottom:8px;">'
            +     '<input type="text" name="tabs['+idx+'][title]" placeholder="Tab title" class="form-control" style="flex:1;">'
            +     '<button type="button" class="btn btn-sm btn-danger btn-remove-tab" title="Remove tab">'
            +       '<i class="voyager-trash"></i>'
            +     '</button>'
            +   '</div>'
            +   '<textarea name="tabs['+idx+'][content]" rows="6" class="form-control" placeholder="Markdown content..."></textarea>'
            + '</div>';
        return $(html);
    }

    function reindexTabs() {
        $tabsContainer.find('.tab-row').each(function (i, row) {
            $(row).find('input[name^="tabs"]').attr('name', 'tabs['+i+'][title]');
            $(row).find('textarea[name^="tabs"]').attr('name', 'tabs['+i+'][content]');
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

    // ------------------------------------------------------------
    // Hierarchy — who nests inside whom
    // ------------------------------------------------------------

    // Collect every nodeId a rule tree refers to, in encounter order.
    function collectRuleRefs(rule, out) {
        out = out || [];
        if (!rule) return out;
        if (rule.kind) {
            if (rule.nodeId) out.push(rule.nodeId);
            return out;
        }
        if (rule.op === 'AND' || rule.op === 'OR') {
            (rule.rules || []).forEach(function (r) { collectRuleRefs(r, out); });
        } else if (rule.op === 'NOT') {
            collectRuleRefs(rule.rule, out);
        }
        return out;
    }

    // parentId for each node:
    //   1. explicit `parentNodeId` when it points at a real node
    //   2. otherwise the first node its visibility rule depends on
    //   3. null for roots (no rule at all)
    //
    // Rule 2 is what produces the natural chain in rules.json —
    // segment depends on zone, localisation on segment, and so on —
    // without every node needing a parentNodeId spelled out.
    var nodeById = {};
    treeData.forEach(function (n) { nodeById[n.id] = n; });

    var parentOf = {};
    treeData.forEach(function (n) {
        var p = n.parentNodeId;
        if (typeof p === 'string' && p !== '' && nodeById[p]) {
            parentOf[n.id] = p;
            return;
        }
        var refs = collectRuleRefs(n.visibilityRule).filter(function (r) {
            return nodeById[r] && r !== n.id;
        });
        parentOf[n.id] = refs.length > 0 ? refs[0] : null;
    });

    // Break any cycle by promoting the offender to a root — a malformed
    // rules.json shouldn't hang the page.
    treeData.forEach(function (n) {
        var seen = {}, cur = n.id;
        while (cur) {
            if (seen[cur]) { parentOf[n.id] = null; break; }
            seen[cur] = true;
            cur = parentOf[cur];
        }
    });

    var childrenOf = {};
    treeData.forEach(function (n) {
        var p = parentOf[n.id];
        if (p === null || p === undefined) return;
        (childrenOf[p] = childrenOf[p] || []).push(n.id);
    });

    // ------------------------------------------------------------
    // Render
    // ------------------------------------------------------------

    function renderNode(node, sel, depth) {
        if (!node) return '';
        // Measurement nodes aren't user-tickable filter tags — their
        // buckets match via product.sizes numeric ranges, not filter_keys.
        if (node.dimensionKey) return '';
        if (!isNodeVisible(node, sel)) return '';

        var isMulti = node.selectionType === 'multi';
        var html = '<div class="ftree-node" data-depth="' + depth + '">';

        html += '<div class="ftree-title">'
             +    escapeHtml(node.title)
             +    '<span class="ftree-meta">' + (isMulti ? 'multi' : 'single') + '</span>'
             +  '</div>';

        html += '<div class="ftree-options">';
        node.children.forEach(function (child) {
            if (!isChildVisible(child, sel)) return;
            var checked = selected.has(child.id);
            html += '<label class="ftree-pill' + (checked ? ' checked' : '') + '"'
                 +    ' data-node="' + escapeAttr(node.id) + '"'
                 +    ' data-child="' + escapeAttr(child.id) + '"'
                 +    ' data-mode="' + escapeAttr(node.selectionType) + '">';
            html +=   '<input type="checkbox" name="filter_keys[]" value="' + escapeAttr(child.id) + '"'
                 +      (checked ? ' checked' : '') + ' style="display:none;">';
            html +=   escapeHtml(child.label);
            html += '</label>';
        });
        html += '</div>';

        // Dependants render INSIDE this node, so the visual hierarchy
        // matches the rules hierarchy instead of a flat list where a
        // pathology sat next to the zone it depends on.
        var kids = (childrenOf[node.id] || [])
            .map(function (id) { return renderNode(nodeById[id], sel, depth + 1); })
            .filter(function (h) { return h !== ''; });

        if (kids.length > 0) {
            html += '<div class="ftree-children">' + kids.join('') + '</div>';
        }

        html += '</div>';
        return html;
    }

    function render() {
        var $tree = $('#filter-tree');
        if (!$tree.length) return;

        var sel = buildSelections();
        var html = '';

        treeData.forEach(function (node) {
            if (parentOf[node.id]) return;   // roots only
            html += renderNode(node, sel, 0);
        });

        $tree.html(html);
        $('#filter-count').text(selected.size);
        $('#filter-export').val(Array.from(selected).join(','));
    }

    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }
    function escapeAttr(s) { return escapeHtml(s); }

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

    $('#filter-copy').on('click', function () {
        var el = document.getElementById('filter-export');
        if (!el) return;
        el.select();
        el.setSelectionRange(0, 99999);
        try { document.execCommand('copy'); } catch (e) {}
        if (navigator.clipboard) { navigator.clipboard.writeText(el.value).catch(function(){}); }
        var $b = $(this), t = $b.html();
        $b.html('<i class="voyager-check"></i> Copié').addClass('btn-success').removeClass('btn-info');
        setTimeout(function () { $b.html(t).removeClass('btn-success').addClass('btn-info'); }, 1200);
    });

    render();

    // ============================================================
    // Dynamic sizes — flat options with per-option measurements
    // ============================================================
    var $optsContainer = $('#options-container');

    // List of {key, title} from rules.json (measurement nodes).
    var measurementDimensions = [];
    try {
        measurementDimensions = JSON.parse($('#measurement-dimensions-data').text() || '[]');
    } catch (e) { measurementDimensions = []; }

    // -----------------------------------------------------------
    // Builders
    // -----------------------------------------------------------

    // <option> markup for the dimension key dropdown.
    function buildDimSelectOptions(currentKey) {
        var html = '<option value="">— select dimension —</option>';
        measurementDimensions.forEach(function (d) {
            var sel = (d.key === currentKey) ? ' selected' : '';
            html += '<option value="' + escapeAttr(d.key) + '"' + sel + '>'
                  + escapeHtml(d.title) + ' (' + escapeHtml(d.key) + ')'
                  + '</option>';
        });
        // If the current key isn't in the known list, surface it so admin
        // can see what's saved even when rules.json doesn't have it yet.
        if (currentKey && !measurementDimensions.some(function (d) { return d.key === currentKey; })) {
            html += '<option value="' + escapeAttr(currentKey) + '" selected>'
                  + '⚠ ' + escapeHtml(currentKey) + ' (not in rules.json)'
                  + '</option>';
        }
        return html;
    }

    function buildMeasurementRow(oi, mi, dimKey, m) {
        m = m || {};
        var html = ''
            + '<div class="measurement-row" style="background:#f4f7fb;padding:10px 12px;border-radius:6px;margin-top:8px;border-left:3px solid #93b5e8;">'
            +   '<div style="display:grid;grid-template-columns:1.6fr 0.7fr 0.7fr 1.4fr auto;gap:8px;align-items:end;">'

            +     '<div>'
            +       '<label style="display:block;font-size:10px;color:#7d8a9c;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;font-weight:700;">Dimension</label>'
            +       '<select class="form-control m-dim" data-dim-select>'
            +         buildDimSelectOptions(dimKey || '')
            +       '</select>'
            +     '</div>'

            +     '<div>'
            +       '<label style="display:block;font-size:10px;color:#7d8a9c;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;font-weight:700;">Min</label>'
            +       '<input type="number" step="any" class="form-control m-min" placeholder="19" value="' + escapeAttr(m.min != null ? String(m.min) : '') + '">'
            +     '</div>'

            +     '<div>'
            +       '<label style="display:block;font-size:10px;color:#7d8a9c;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;font-weight:700;">Max</label>'
            +       '<input type="number" step="any" class="form-control m-max" placeholder="21" value="' + escapeAttr(m.max != null ? String(m.max) : '') + '">'
            +     '</div>'

            +     '<div>'
            +       '<label style="display:block;font-size:10px;color:#7d8a9c;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;font-weight:700;">Display range</label>'
            +       '<input type="text" class="form-control m-display" placeholder="19 cm - 21 cm" value="' + escapeAttr(m.displayRange || '') + '">'
            +     '</div>'

            +     '<div style="padding-bottom:1px;">'
            +       '<button type="button" class="btn btn-xs btn-danger btn-remove-measurement" title="Remove measurement"><i class="voyager-trash"></i></button>'
            +     '</div>'
            +   '</div>'
            + '</div>';
        return $(html);
    }

    function buildOptionRow(oi, opt) {
        opt = opt || {};
        var $row = $(''
            + '<div class="option-row" style="border:1px solid #d8dde6;border-radius:8px;padding:14px;margin-bottom:12px;background:#fcfdff;">'

            +   '<div style="display:grid;grid-template-columns:1fr 1.4fr auto;gap:10px;align-items:end;">'
            +     '<div>'
            +       '<label style="display:block;font-size:10px;color:#7d8a9c;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;font-weight:700;">Label</label>'
            +       '<input type="text" class="form-control opt-label" placeholder="Taille 1, S, M…" value="' + escapeAttr(opt.label || '') + '">'
            +     '</div>'
            +     '<div>'
            +       '<label style="display:block;font-size:10px;color:#7d8a9c;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;font-weight:700;">Display range (summary)</label>'
            +       '<input type="text" class="form-control opt-display" placeholder="19-21 cm" value="' + escapeAttr(opt.displayRange || '') + '">'
            +     '</div>'
            +     '<div>'
            +       '<button type="button" class="btn btn-xs btn-danger btn-remove-option" title="Remove option"><i class="voyager-trash"></i></button>'
            +     '</div>'
            +   '</div>'

            +   '<div style="margin-top:14px;padding-top:10px;border-top:1px dashed #e0e6ee;">'
            +     '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">'
            +       '<span style="font-size:11px;color:#1d3557;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Measurements</span>'
            +     '</div>'
            +     '<div class="measurements-container"></div>'
            +     '<button type="button" class="btn btn-xs btn-default btn-add-measurement" style="margin-top:8px;">'
            +       '<i class="voyager-plus"></i> Add measurement'
            +     '</button>'
            +   '</div>'

            + '</div>'
        );

        // Hydrate measurements from the option's `measurements` object.
        // Object keys (= dimensionKey) become rows.
        var $mCont = $row.find('.measurements-container');
        var measurements = opt.measurements || {};
        var keys = Object.keys(measurements);
        keys.forEach(function (k, mi) {
            $mCont.append(buildMeasurementRow(oi, mi, k, measurements[k]));
        });

        return $row;
    }

    // -----------------------------------------------------------
    // Submission shaping — instead of relying on raw name=
    // attributes (which is hard with object-keyed measurements),
    // we serialize the options block into hidden inputs whose
    // names match the Laravel validation rules at submit time.
    // This way we don't have to keep `name=` attrs in sync as
    // measurements get added/removed/reordered.
    // -----------------------------------------------------------

    function collectAndSerialize() {
        // Remove any previously-injected hidden fields for sizes
        $('input[data-sizes-hidden]').remove();

        var $form = $optsContainer.closest('form');
        var oi = 0;

        $optsContainer.find('.option-row').each(function () {
            var $opt = $(this);
            var label   = $opt.find('.opt-label').val();
            var display = $opt.find('.opt-display').val();

            // Hidden inputs for the option scalars
            $form.append(makeHidden('sizes_options['+oi+'][label]', label));
            $form.append(makeHidden('sizes_options['+oi+'][displayRange]', display));

            $opt.find('.measurement-row').each(function () {
                var $m = $(this);
                var dim = $m.find('.m-dim').val();
                var min = $m.find('.m-min').val();
                var max = $m.find('.m-max').val();
                var dsp = $m.find('.m-display').val();
                if (!dim) return; // skip rows without a dimension selected

                // Bracket-key the measurement by dimension key — Laravel
                // happily decodes this into an associative array.
                var prefix = 'sizes_options['+oi+'][measurements]['+dim+']';
                $form.append(makeHidden(prefix + '[min]', min));
                $form.append(makeHidden(prefix + '[max]', max));
                $form.append(makeHidden(prefix + '[displayRange]', dsp));
            });

            oi++;
        });
    }

    function makeHidden(name, value) {
        var $i = $('<input type="hidden">');
        $i.attr('name', name);
        $i.attr('data-sizes-hidden', '1');
        $i.val(value == null ? '' : String(value));
        return $i;
    }

    // -----------------------------------------------------------
    // Hydrate initial state
    // -----------------------------------------------------------
    var initialSizes = null;
    try { initialSizes = JSON.parse($('#sizes-initial-data').text() || 'null'); } catch (e) {}
    var initialOpts = (initialSizes && Array.isArray(initialSizes.options))
        ? initialSizes.options
        : [];

    initialOpts.forEach(function (o, oi) {
        $optsContainer.append(buildOptionRow(oi, o));
    });

    // -----------------------------------------------------------
    // Event handlers
    // -----------------------------------------------------------

    $('#btn-add-option').on('click', function () {
        var oi = $optsContainer.find('.option-row').length;
        $optsContainer.append(buildOptionRow(oi, null));
    });

    $optsContainer.on('click', '.btn-remove-option', function () {
        $(this).closest('.option-row').remove();
    });

    $optsContainer.on('click', '.btn-add-measurement', function () {
        var $mCont = $(this).siblings('.measurements-container');
        var $opt = $(this).closest('.option-row');
        var oi = $optsContainer.find('.option-row').index($opt);
        var mi = $mCont.find('.measurement-row').length;
        $mCont.append(buildMeasurementRow(oi, mi, '', null));
    });

    $optsContainer.on('click', '.btn-remove-measurement', function () {
        $(this).closest('.measurement-row').remove();
    });

    // Serialize sizes right before form submission.
    $optsContainer.closest('form').on('submit', function () {
        collectAndSerialize();
    });

    // ============================================================
    // Markdown parser modal
    // ============================================================
    var $mdModal    = $('#markdown-modal');
    var $mdInput    = $('#md-input');
    var $mdWarnings = $('#md-warnings');

    $('#btn-parse-markdown').on('click', function () {
        $mdInput.val('');
        $mdWarnings.html('');
        $mdModal.css('display', 'block');
    });
    $('#md-close, #md-cancel').on('click', function () {
        $mdModal.css('display', 'none');
    });

    $('#md-replace').on('click', function () {
        applyMarkdownParse(true);
    });
    $('#md-append').on('click', function () {
        applyMarkdownParse(false);
    });

    function applyMarkdownParse(replaceAll) {
        var result = parseMarkdownTable($mdInput.val());
        if (result.warnings.length > 0) {
            var html = '<strong>Warnings:</strong><ul style="margin:4px 0 0 18px;padding:0;">';
            result.warnings.forEach(function (w) {
                html += '<li>' + escapeHtml(w) + '</li>';
            });
            html += '</ul>';
            $mdWarnings.html(html);
        } else {
            $mdWarnings.html('');
        }

        if (result.options.length === 0) {
            $mdWarnings.append('<div style="color:#a40000;margin-top:6px;"><strong>No options parsed.</strong> Check the table format.</div>');
            return;
        }

        if (replaceAll) {
            $optsContainer.empty();
        }
        var startIdx = $optsContainer.find('.option-row').length;
        result.options.forEach(function (o, i) {
            $optsContainer.append(buildOptionRow(startIdx + i, o));
        });
        $mdModal.css('display', 'none');
    }

    // -----------------------------------------------------------
    // Markdown table parser — produces the same shape we'd save:
    //   { options: [ { label, displayRange, measurements: {...} } ] }
    // -----------------------------------------------------------
    function parseMarkdownTable(src) {
        var warnings = [];
        var options = [];
        if (!src || !src.trim()) return { options: options, warnings: warnings };

        // Split into non-empty trimmed lines that look like table rows.
        var lines = src.split(/\r?\n/)
            .map(function (l) { return l.trim(); })
            .filter(function (l) { return l.length > 0 && l.indexOf('|') !== -1; });

        if (lines.length < 2) {
            warnings.push('Need at least a header row and one data row.');
            return { options: options, warnings: warnings };
        }

        // Trim wrapping pipes and split each row by | into cells
        function rowCells(line) {
            // Strip leading/trailing |
            var s = line.replace(/^\|/, '').replace(/\|$/, '');
            return s.split('|').map(function (c) { return c.trim(); });
        }

        var header = rowCells(lines[0]);

        // Skip the separator row (e.g. | --- | --- |) if present.
        var dataStart = 1;
        if (/^[\s\-:|]+$/.test(lines[1].replace(/\|/g, ' '))) {
            dataStart = 2;
        } else {
            warnings.push('No separator row (| --- |) detected — proceeding anyway.');
        }

        if (header.length < 2) {
            warnings.push('Header must have at least 2 columns (label + 1 measurement).');
            return { options: options, warnings: warnings };
        }

        // First column = size label. Remaining columns = measurements.
        var dimKeys = [];
        for (var c = 1; c < header.length; c++) {
            var raw = header[c];
            var key = normalizeDimKey(raw);
            if (!key) {
                warnings.push('Column "' + raw + '" produced an empty dimensionKey — skipped.');
                dimKeys.push(null);
                continue;
            }
            // Warn when the key isn't in rules.json
            if (!measurementDimensions.some(function (d) { return d.key === key; })) {
                warnings.push('Dimension "' + key + '" (column "' + raw + '") is not in rules.json — saved anyway.');
            }
            dimKeys.push(key);
        }

        for (var r = dataStart; r < lines.length; r++) {
            var cells = rowCells(lines[r]);
            var label = cells[0] || '';
            if (!label) continue;

            var measurements = {};
            var displayParts = [];

            for (var c = 1; c < cells.length && c < header.length; c++) {
                var key = dimKeys[c - 1];
                if (!key) continue;
                var cell = cells[c];
                if (!cell || /^[—\-n\/an\.a]*$/i.test(cell.replace(/\s/g, ''))) continue;
                var range = parseRange(cell);
                if (!range) {
                    warnings.push('Row "' + label + '" / column "' + header[c] + '": could not parse "' + cell + '" — skipped.');
                    continue;
                }
                measurements[key] = {
                    min: range.min,
                    max: range.max,
                    displayRange: cell,
                };
                displayParts.push(cell);
            }

            options.push({
                label: label,
                displayRange: displayParts.join(' / '),
                measurements: measurements,
            });
        }

        return { options: options, warnings: warnings };
    }

    // Normalize header text → snake_case ascii key.
    // "Tour de cheville" → "tour_de_cheville", "Périmètre" → "perimetre"
    function normalizeDimKey(s) {
        if (!s) return '';
        // Strip accents
        var ascii = s.normalize ? s.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : s;
        return ascii
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    // Parse a single range cell into { min, max }.
    // Supports: "19 cm - 21 cm", "19-21 cm", "< 22 cm", "> 30 cm",
    //           "≤ 22 cm", "≥ 30 cm", "22 cm" (single), FR decimal "19,5".
    function parseRange(s) {
        if (!s) return null;
        // Normalize: replace commas in numbers with dots for parsing
        var raw = s.replace(/(\d),(\d)/g, '$1.$2');

        // Strip units we don't need to parse — keep numbers + operators
        var num = '(\\d+(?:\\.\\d+)?)';
        var m;

        // "< 22" / "≤ 22" / "<= 22"
        m = raw.match(new RegExp('^\\s*[<≤]=?\\s*' + num + '\\s*[a-zA-Z%°]*\\s*$'));
        if (m) return { min: 0, max: parseFloat(m[1]) };

        // "> 30" / "≥ 30" / ">= 30"
        m = raw.match(new RegExp('^\\s*[>≥]=?\\s*' + num + '\\s*[a-zA-Z%°]*\\s*$'));
        if (m) return { min: parseFloat(m[1]), max: 999 };

        // "19 cm - 21 cm" / "19 - 21" / "19-21 cm" / "19 to 21 cm"
        m = raw.match(new RegExp(num + '\\s*(?:cm|mm|m|in)?\\s*(?:-|–|—|to|à)\\s*' + num + '\\s*[a-zA-Z%°]*'));
        if (m) return { min: parseFloat(m[1]), max: parseFloat(m[2]) };

        // Single value "22 cm"
        m = raw.match(new RegExp('^\\s*' + num + '\\s*[a-zA-Z%°]*\\s*$'));
        if (m) {
            var v = parseFloat(m[1]);
            return { min: v, max: v };
        }

        return null;
    }

    // ============================================================
    // Protocol groups — flat checkbox tree grouped by protocol > phase.
    // Only filter-type groups are rendered (info groups can't be
    // assigned to a product). Posts as `groups[]=<id>` so the
    // controller picks them up via array validation.
    // ============================================================
    (function () {
        var $treeEl = $('#protocol-groups-tree');
        if ($treeEl.length === 0) return; // no protocols configured

        var protocols = [];
        var initialSelected = [];
        try {
            protocols = JSON.parse($('#protocols-tree-data').text() || '[]');
        } catch (e) { protocols = []; }
        try {
            initialSelected = JSON.parse($('#groups-selected-data').text() || '[]');
        } catch (e) { initialSelected = []; }
        if (!Array.isArray(protocols)) protocols = [];
        if (!Array.isArray(initialSelected)) initialSelected = [];

        var selectedGroups = new Set(initialSelected);

        // Escape any value that lands inside HTML text — group/phase
        // titles come from JSON, so a stray <script> would otherwise
        // execute. Cheap and good enough for admin-side rendering.
        function esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function render() {
            var html = '';
            var hasAnyFilter = false;

            protocols.forEach(function (proto) {
                var protoTitle = esc(proto.title || proto.id || '(untitled protocol)');
                html += '<div style="margin-bottom:16px;border:1px solid #eaeef3;border-radius:6px;overflow:hidden;">';
                html += '<div style="background:#f7fafc;padding:8px 12px;font-weight:600;color:#1d3557;font-size:13px;border-bottom:1px solid #eaeef3;">' + protoTitle + '</div>';
                html += '<div style="padding:10px 14px;">';

                var phases = Array.isArray(proto.phases) ? proto.phases : [];
                if (phases.length === 0) {
                    html += '<p class="text-muted" style="margin:0;font-size:12px;">No phases.</p>';
                }

                phases.forEach(function (phase) {
                    var phaseTitle = esc(phase.title || phase.id || '(untitled phase)');
                    var groups = Array.isArray(phase.groups) ? phase.groups : [];
                    var filterGroups = groups.filter(function (g) { return g && g.type === 'filter'; });

                    html += '<div style="margin-bottom:8px;">';
                    html += '<div style="font-size:12px;font-weight:600;color:#5c6b7e;margin-bottom:4px;">' + phaseTitle + '</div>';

                    if (filterGroups.length === 0) {
                        html += '<p class="text-muted" style="margin:0 0 4px;font-size:11.5px;font-style:italic;">No filter groups in this phase.</p>';
                    } else {
                        html += '<div style="display:flex;flex-wrap:wrap;gap:6px 14px;">';
                        filterGroups.forEach(function (g) {
                            hasAnyFilter = true;
                            var gid   = String(g.id || '');
                            var label = esc(g.label || gid);
                            var checked = selectedGroups.has(gid) ? ' checked' : '';
                            html += '<label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;margin:0;font-size:13px;cursor:pointer;">';
                            html += '<input type="checkbox" class="group-checkbox" name="groups[]" value="' + esc(gid) + '"' + checked + ' style="margin:0;">';
                            html += '<span>' + label + '</span>';
                            html += '</label>';
                        });
                        html += '</div>';
                    }

                    html += '</div>';
                });

                html += '</div></div>';
            });

            if (!hasAnyFilter) {
                html = '<p class="text-muted" style="margin:0;font-size:12.5px;">No filter-type groups defined across any protocol. Add at least one filter group in <code>protocols.json</code>.</p>';
            }

            $treeEl.html(html);
            updateCount();
        }

        function updateCount() {
            $('#group-count').text(selectedGroups.size);
        }

        // Sync the selectedGroups Set with checkbox state on change.
        $treeEl.on('change', '.group-checkbox', function () {
            var id = $(this).val();
            if (this.checked) selectedGroups.add(id);
            else               selectedGroups.delete(id);
            updateCount();
        });

        $('#group-clear').on('click', function (e) {
            e.preventDefault();
            selectedGroups.clear();
            $treeEl.find('.group-checkbox').prop('checked', false);
            updateCount();
        });

        render();
    })();

});
</script>