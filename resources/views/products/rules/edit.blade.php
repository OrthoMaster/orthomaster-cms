<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orthomaster Logic Tree Json</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg: #0f1419;
            --panel: #1a1f29;
            --panel-2: #232936;
            --border: #2d3543;
            --text: #e4e6eb;
            --text-dim: #8b95a8;
            --text-faint: #5c6473;
            --accent: #5eead4;
            --accent-dim: rgba(94, 234, 212, 0.12);
            --danger: #f87171;
            --warning: #fbbf24;
        }

        html,
        body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            font-size: 13px;
            background: var(--bg);
            color: var(--text);
        }

        body {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        header {
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--panel);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        header h1 {
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        header h1 em {
            color: var(--accent);
            font-style: normal;
        }

        header .toolbar {
            display: flex;
            gap: 8px;
        }

        main {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 480px;
            gap: 1px;
            overflow: hidden;
            background: var(--border);
        }

        .panel {
            background: var(--bg);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .panel-header {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--panel);
            flex-shrink: 0;
        }

        .panel-title {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text-dim);
        }

        .panel-spacer {
            flex: 1;
        }

        .panel-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }

        /* Buttons */
        button {
            background: var(--panel-2);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 5px 11px;
            border-radius: 5px;
            cursor: pointer;
            font: inherit;
            font-size: 12px;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        button:hover:not(:disabled) {
            background: var(--accent-dim);
            border-color: var(--accent);
        }

        button:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }

        button.primary {
            background: var(--accent);
            color: #0f1419;
            border-color: var(--accent);
            font-weight: 600;
        }

        button.primary:hover:not(:disabled) {
            background: #4dd4be;
        }

        button.icon {
            padding: 3px 7px;
            font-size: 12px;
            line-height: 1;
        }

        button.danger {
            color: var(--danger);
        }

        button.danger:hover:not(:disabled) {
            background: rgba(248, 113, 113, 0.1);
            border-color: var(--danger);
        }

        button.small {
            padding: 3px 8px;
            font-size: 11px;
        }

        /* Inputs */
        input,
        select,
        textarea {
            background: var(--panel);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 5px 8px;
            border-radius: 4px;
            font: inherit;
            font-size: 12px;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent);
        }

        select {
            cursor: pointer;
        }

        /* Node card */
        .node-card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 14px;
            overflow: hidden;
        }

        .node-header {
            padding: 9px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--panel-2);
            border-bottom: 1px solid var(--border);
        }

        /* When collapsed, drop the bottom border so it sits flush */
        .node-card.collapsed .node-header {
            border-bottom: none;
        }

        .id-display {
            font-family: 'Menlo', 'Monaco', 'Consolas', monospace;
            font-size: 11px;
            color: var(--accent);
            background: rgba(94, 234, 212, 0.08);
            padding: 2px 7px;
            border-radius: 3px;
        }

        .id-display.empty {
            color: var(--text-faint);
            background: var(--panel);
        }

        .title-display {
            font-weight: 600;
            color: var(--text);
        }

        .title-display.empty {
            color: var(--text-faint);
            font-style: italic;
            font-weight: 500;
        }

        /* Small badge that shows children count when node is collapsed */
        .child-count-pill {
            font-size: 10px;
            color: var(--text-dim);
            background: var(--panel);
            border: 1px solid var(--border);
            padding: 1px 7px;
            border-radius: 999px;
            font-weight: 500;
        }

        .node-body {
            padding: 14px;
            display: grid;
            gap: 10px;
        }

        .field-row {
            display: grid;
            grid-template-columns: 80px 1fr;
            align-items: center;
            gap: 10px;
        }

        .field-row>label {
            font-size: 11px;
            color: var(--text-dim);
            font-weight: 500;
        }

        .field-row>input,
        .field-row>select {
            width: 100%;
        }

        .section-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-faint);
            margin-top: 6px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-label .count {
            color: var(--text-dim);
            font-weight: 500;
        }

        /* Rule editor */
        .rule {
            border-left: 2px solid var(--border);
            padding: 6px 0 6px 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .rule.compound {
            border-left-color: var(--accent);
        }

        .rule-header {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .rule-header select {
            font-size: 11px;
            padding: 4px 6px;
        }

        .sub-rules {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 2px;
        }

        .sub-rule-wrap {
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }

        .sub-rule-wrap>.rule {
            flex: 1;
            min-width: 0;
        }

        .rule-warn {
            color: var(--danger);
            font-size: 11px;
            font-style: italic;
        }

        /* Children list */
        .children-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .child-card {
            background: var(--panel-2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 10px;
        }

        .child-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }

        .child-row label {
            font-size: 11px;
            color: var(--text-dim);
        }

        .child-row .id-input {
            width: 200px;
        }

        .child-row .label-input {
            flex: 1;
            min-width: 100px;
        }

        /* JSON panel */
        #json-text {
            flex: 1;
            width: 100%;
            border: none;
            background: var(--bg);
            color: var(--text);
            padding: 14px 18px;
            font-family: 'Menlo', 'Monaco', 'Consolas', monospace;
            font-size: 12px;
            line-height: 1.5;
            resize: none;
            tab-size: 2;
            min-height: 0;
        }

        #json-text:focus {
            outline: none;
        }

        .json-status {
            padding: 9px 16px;
            border-top: 1px solid var(--border);
            background: var(--panel);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 6px var(--accent);
            flex-shrink: 0;
        }

        .status-dot.dirty {
            background: var(--warning);
            box-shadow: 0 0 6px var(--warning);
        }

        .status-dot.error {
            background: var(--danger);
            box-shadow: 0 0 6px var(--danger);
        }

        .status-text {
            flex: 1;
            color: var(--text-dim);
            font-size: 11px;
        }

        .status-text.error {
            color: var(--danger);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-faint);
        }

        .empty-state p {
            margin-bottom: 8px;
        }

        /* Responsive */
        @media (max-width: 900px) {
            main {
                grid-template-columns: 1fr;
                grid-template-rows: 1fr 320px;
            }
        }
    </style>
</head>

<body>
    <header>
        <h1>Orthomaster <em>Logic Tree Json</em></h1>
        <div class="toolbar">
            <button id="btn-load">Load</button>
            <button id="btn-collapse-all" title="Collapse all nodes">Collapse all</button>
            <button id="btn-expand-all" title="Expand all nodes">Expand all</button>
            <button id="btn-clear" class="danger">Clear all</button>
        </div>
    </header>

    <main>
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Nodes</span>
                <span class="panel-spacer"></span>
                <button id="btn-add-node" class="primary">+ Add node</button>
            </div>
            <div class="panel-body" id="builder"></div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">JSON</span>
                <span class="panel-spacer"></span>
                <button id="btn-copy">Copy</button>
                <button id="btn-apply" class="primary">Apply</button>
            </div>
            <textarea id="json-text" spellcheck="false" wrap="off"></textarea>
            <div class="json-status">
                <span class="status-dot" id="status-dot"></span>
                <span class="status-text" id="status-text">In sync</span>
            </div>
        </div>
    </main>

    <script>
        /* ============================================================
           STATE
           ============================================================ */
        let nodes = [];
        let textareaIsDirty = false;

        // Per-node UI state — collapsed nodes hide their body.
        // Stored by object identity so it doesn't bleed into JSON output.
        // WeakSet membership = collapsed; absent = expanded (default).
        const collapsedNodes = new WeakSet();

        // URL endpoint that serves storage/app/product/rules.json
        // Laravel options:
        //   1) Symlink:  storage/app/public/product/rules.json + `php artisan storage:link`
        //                → accessible at /storage/product/rules.json
        //   2) Route:    Route::get('/product/rules.json',
        //                  fn() => response()->file(storage_path('app/product/rules.json')));
        // Adjust if your setup differs.
        const RULES_URL = "{{ route('rules.default') }}";

        /* ============================================================
           DOM HELPERS
           ============================================================ */
        function el(tag, className, text) {
            const e = document.createElement(tag);
            if (className) e.className = className;
            if (text != null) e.textContent = text;
            return e;
        }

        function makeInput(value, oninput, placeholder) {
            const i = document.createElement('input');
            i.value = value || '';
            if (placeholder) i.placeholder = placeholder;
            i.addEventListener('input', () => oninput(i.value));
            return i;
        }

        function makeSelect(value, options, onchange) {
            const s = document.createElement('select');
            options.forEach(opt => {
                const o = document.createElement('option');
                if (typeof opt === 'string') {
                    o.value = opt; o.textContent = opt;
                } else {
                    o.value = opt.value; o.textContent = opt.label;
                }
                s.appendChild(o);
            });
            s.value = value;
            s.addEventListener('change', () => onchange(s.value));
            return s;
        }

        function makeBtn(text, onclick, className, title) {
            const b = document.createElement('button');
            b.textContent = text;
            if (className) b.className = className;
            if (title) b.title = title;
            b.addEventListener('click', onclick);
            return b;
        }

        /* ============================================================
           RULE EDITOR
           ============================================================ */
        const RULE_TYPES = [
            { value: 'none', label: 'None (always visible)' },
            { value: 'selected', label: 'Specific child selected' },
            { value: 'any_selected', label: 'Any child selected' },
            { value: 'AND', label: 'AND (all must match)' },
            { value: 'OR', label: 'OR (any must match)' },
            { value: 'NOT', label: 'NOT (negate)' }
        ];

        function ruleTypeOf(rule) {
            if (rule == null) return 'none';
            if (rule.kind === 'selected') return 'selected';
            if (rule.kind === 'any_selected') return 'any_selected';
            if (rule.op === 'AND') return 'AND';
            if (rule.op === 'OR') return 'OR';
            if (rule.op === 'NOT') return 'NOT';
            return 'none';
        }

        function blankRuleOf(type) {
            switch (type) {
                case 'none': return null;
                case 'selected': return { kind: 'selected', nodeId: '', childId: '' };
                case 'any_selected': return { kind: 'any_selected', nodeId: '' };
                case 'AND': return { op: 'AND', rules: [] };
                case 'OR': return { op: 'OR', rules: [] };
                case 'NOT': return { op: 'NOT', rule: null };
            }
        }

        function nodeOptions(currentNodeId) {
            const opts = [{ value: '', label: '— select node —' }];
            nodes.forEach(n => {
                if (n.id && n.id !== currentNodeId) {
                    opts.push({ value: n.id, label: n.title ? `${n.id} — ${n.title}` : n.id });
                }
            });
            return opts;
        }

        function childOptions(nodeId) {
            const target = nodes.find(n => n.id === nodeId);
            const opts = [{ value: '', label: '— select child —' }];
            if (target && target.children) {
                target.children.forEach(c => {
                    if (c.id) opts.push({ value: c.id, label: c.label ? `${c.id} — ${c.label}` : c.id });
                });
            }
            return opts;
        }

        function renderRuleEditor(rule, onChange, currentNodeId) {
            const isCompound = rule && (rule.op === 'AND' || rule.op === 'OR' || rule.op === 'NOT');
            const wrap = el('div', 'rule' + (isCompound ? ' compound' : ''));

            const header = el('div', 'rule-header');

            const typeSel = makeSelect(ruleTypeOf(rule), RULE_TYPES, v => onChange(blankRuleOf(v)));
            header.appendChild(typeSel);

            if (rule && rule.kind === 'selected') {
                header.appendChild(makeSelect(rule.nodeId || '', nodeOptions(currentNodeId), v => {
                    onChange({ kind: 'selected', nodeId: v, childId: '' });
                }));
                if (rule.nodeId) {
                    const target = nodes.find(n => n.id === rule.nodeId);
                    if (!target) {
                        header.appendChild(el('span', 'rule-warn', `(node "${rule.nodeId}" not found)`));
                    } else {
                        header.appendChild(makeSelect(rule.childId || '', childOptions(rule.nodeId), v => {
                            onChange({ kind: 'selected', nodeId: rule.nodeId, childId: v });
                        }));
                        if (rule.childId && !target.children?.some(c => c.id === rule.childId)) {
                            header.appendChild(el('span', 'rule-warn', `(child "${rule.childId}" not found)`));
                        }
                    }
                }
            } else if (rule && rule.kind === 'any_selected') {
                header.appendChild(makeSelect(rule.nodeId || '', nodeOptions(currentNodeId), v => {
                    onChange({ kind: 'any_selected', nodeId: v });
                }));
                if (rule.nodeId && !nodes.find(n => n.id === rule.nodeId)) {
                    header.appendChild(el('span', 'rule-warn', `(node "${rule.nodeId}" not found)`));
                }
            }

            wrap.appendChild(header);

            if (rule && (rule.op === 'AND' || rule.op === 'OR')) {
                const sub = el('div', 'sub-rules');
                rule.rules.forEach((r, i) => {
                    const row = el('div', 'sub-rule-wrap');
                    row.appendChild(renderRuleEditor(r, newR => {
                        const newRules = [...rule.rules];
                        newRules[i] = newR;
                        onChange({ ...rule, rules: newRules });
                    }, currentNodeId));
                    row.appendChild(makeBtn('✕', () => {
                        const newRules = rule.rules.filter((_, j) => j !== i);
                        onChange({ ...rule, rules: newRules });
                    }, 'icon danger'));
                    sub.appendChild(row);
                });
                sub.appendChild(makeBtn('+ sub-rule', () => {
                    onChange({ ...rule, rules: [...rule.rules, null] });
                }, 'small'));
                wrap.appendChild(sub);
            } else if (rule && rule.op === 'NOT') {
                const sub = el('div', 'sub-rules');
                sub.appendChild(renderRuleEditor(rule.rule, newR => {
                    onChange({ op: 'NOT', rule: newR });
                }, currentNodeId));
                wrap.appendChild(sub);
            }

            return wrap;
        }

        /* ============================================================
           BUILDER
           ============================================================ */
        function renderBuilder() {
            const root = document.getElementById('builder');
            root.innerHTML = '';

            if (nodes.length === 0) {
                const empty = el('div', 'empty-state');
                empty.appendChild(el('p', null, 'No nodes yet.'));
                empty.appendChild(el('p', null, 'Click "+ Add node" to start, or "Load" to fetch from the server.'));
                root.appendChild(empty);
                return;
            }

            nodes.forEach((node, idx) => root.appendChild(renderNodeCard(node, idx)));
        }

        function renderNodeCard(node, idx) {
            const isCollapsed = collapsedNodes.has(node);
            const card = el('div', 'node-card' + (isCollapsed ? ' collapsed' : ''));

            // Header
            const header = el('div', 'node-header');
            const idDisp = el('span', 'id-display' + (node.id ? '' : ' empty'), node.id || '(no id)');
            header.appendChild(idDisp);
            const titleDisp = el('span', 'title-display' + (node.title ? '' : ' empty'), node.title || '(no title)');
            header.appendChild(titleDisp);

            // When collapsed, show a small pill with children count so you still see at-a-glance info.
            if (isCollapsed) {
                const count = (node.children || []).length;
                header.appendChild(el('span', 'child-count-pill', `${count} child${count === 1 ? '' : 'ren'}`));
            }

            const spacer = el('span'); spacer.style.flex = '1';
            header.appendChild(spacer);

            // NEW: expand/collapse toggle (placed first so destructive ✕ stays at the far right).
            const toggleBtn = makeBtn(
                isCollapsed ? '▸' : '▾',
                () => {
                    if (isCollapsed) collapsedNodes.delete(node);
                    else collapsedNodes.add(node);
                    renderBuilder(); // local rerender — JSON unchanged, no need to sync
                },
                'icon',
                isCollapsed ? 'Expand' : 'Collapse'
            );
            header.appendChild(toggleBtn);

            const upBtn = makeBtn('↑', () => {
                if (idx === 0) return;
                [nodes[idx - 1], nodes[idx]] = [nodes[idx], nodes[idx - 1]];
                rerenderAll();
            }, 'icon');
            upBtn.disabled = idx === 0;
            header.appendChild(upBtn);

            const downBtn = makeBtn('↓', () => {
                if (idx === nodes.length - 1) return;
                [nodes[idx + 1], nodes[idx]] = [nodes[idx], nodes[idx + 1]];
                rerenderAll();
            }, 'icon');
            downBtn.disabled = idx === nodes.length - 1;
            header.appendChild(downBtn);

            header.appendChild(makeBtn('✕', () => {
                if (confirm(`Delete node "${node.id || idx}"?`)) {
                    collapsedNodes.delete(node);
                    nodes.splice(idx, 1);
                    rerenderAll();
                }
            }, 'icon danger'));

            card.appendChild(header);

            // Body — only build if expanded
            if (!isCollapsed) {
                const body = el('div', 'node-body');

                // ID
                const idRow = el('div', 'field-row');
                idRow.appendChild(el('label', null, 'ID'));
                idRow.appendChild(makeInput(node.id, v => {
                    node.id = v;
                    idDisp.textContent = v || '(no id)';
                    idDisp.classList.toggle('empty', !v);
                    syncJsonFromNodes();
                }, 'unique node id'));
                body.appendChild(idRow);

                // Title
                const titleRow = el('div', 'field-row');
                titleRow.appendChild(el('label', null, 'Title'));
                titleRow.appendChild(makeInput(node.title, v => {
                    node.title = v;
                    titleDisp.textContent = v || '(no title)';
                    titleDisp.classList.toggle('empty', !v);
                    syncJsonFromNodes();
                }, 'displayed name'));
                body.appendChild(titleRow);

                // Selection type
                const selRow = el('div', 'field-row');
                selRow.appendChild(el('label', null, 'Selection'));
                selRow.appendChild(makeSelect(node.selectionType || 'single', [
                    { value: 'single', label: 'single (1 choice)' },
                    { value: 'multi', label: 'multi (multiple choices)' }
                ], v => {
                    node.selectionType = v;
                    syncJsonFromNodes();
                }));
                body.appendChild(selRow);

                // Visibility rule
                body.appendChild(el('div', 'section-label', 'Visibility rule'));
                body.appendChild(renderRuleEditor(node.visibilityRule || null, newRule => {
                    node.visibilityRule = newRule;
                    rerenderAll();
                }, node.id));

                // Children section label
                const childLabel = el('div', 'section-label');
                childLabel.appendChild(document.createTextNode('Children'));
                childLabel.appendChild(el('span', 'count', `(${(node.children || []).length})`));
                body.appendChild(childLabel);

                // Children list
                const childrenList = el('div', 'children-list');
                (node.children || []).forEach((child, ci) => {
                    childrenList.appendChild(renderChildCard(node, child, ci));
                });
                body.appendChild(childrenList);

                body.appendChild(makeBtn('+ Add child', () => {
                    if (!node.children) node.children = [];
                    node.children.push({ id: '', label: '' });
                    rerenderAll();
                }, 'small'));

                card.appendChild(body);
            }

            return card;
        }

        function renderChildCard(node, child, ci) {
            const card = el('div', 'child-card');

            const row = el('div', 'child-row');

            row.appendChild(el('label', null, 'ID'));
            const idInput = makeInput(child.id, v => { child.id = v; syncJsonFromNodes(); }, 'unique within parent');
            idInput.className = 'id-input';
            row.appendChild(idInput);

            row.appendChild(el('label', null, 'Label'));
            const labelInput = makeInput(child.label, v => { child.label = v; syncJsonFromNodes(); }, 'displayed text');
            labelInput.className = 'label-input';
            row.appendChild(labelInput);

            // Up/Down for children
            const upBtn = makeBtn('↑', () => {
                if (ci === 0) return;
                [node.children[ci - 1], node.children[ci]] = [node.children[ci], node.children[ci - 1]];
                rerenderAll();
            }, 'icon');
            upBtn.disabled = ci === 0;
            row.appendChild(upBtn);

            const downBtn = makeBtn('↓', () => {
                if (ci === node.children.length - 1) return;
                [node.children[ci + 1], node.children[ci]] = [node.children[ci], node.children[ci + 1]];
                rerenderAll();
            }, 'icon');
            downBtn.disabled = ci === node.children.length - 1;
            row.appendChild(downBtn);

            row.appendChild(makeBtn('✕', () => {
                node.children.splice(ci, 1);
                rerenderAll();
            }, 'icon danger'));

            card.appendChild(row);

            card.appendChild(el('div', 'section-label', 'Rule'));
            card.appendChild(renderRuleEditor(child.visibilityRule || null, newRule => {
                if (newRule == null) delete child.visibilityRule;
                else child.visibilityRule = newRule;
                rerenderAll();
            }, node.id));

            return card;
        }

        /* ============================================================
           JSON SYNC
           ============================================================ */
        function syncJsonFromNodes() {
            const ta = document.getElementById('json-text');
            ta.value = JSON.stringify(nodes, null, 2);
            textareaIsDirty = false;
            updateStatus('synced');
        }

        function applyJsonFromTextarea() {
            const ta = document.getElementById('json-text');
            try {
                const parsed = JSON.parse(ta.value);
                if (!Array.isArray(parsed)) throw new Error('Top level must be an array of nodes.');
                nodes = parsed;
                // Fresh objects → start expanded. (WeakSet auto-cleans the old ones.)
                textareaIsDirty = false;
                rerenderAll();
            } catch (e) {
                updateStatus('error', e.message);
            }
        }

        function updateStatus(state, msg) {
            const dot = document.getElementById('status-dot');
            const text = document.getElementById('status-text');
            dot.className = 'status-dot';
            text.className = 'status-text';
            if (state === 'synced') {
                text.textContent = `In sync · ${nodes.length} node${nodes.length === 1 ? '' : 's'}`;
            } else if (state === 'dirty') {
                dot.classList.add('dirty');
                text.textContent = 'Unsaved JSON edits — click Apply';
            } else if (state === 'error') {
                dot.classList.add('error');
                text.classList.add('error');
                text.textContent = 'Parse error: ' + msg;
            } else if (state === 'loading') {
                dot.classList.add('dirty');
                text.textContent = msg || 'Loading…';
            } else if (state === 'load-error') {
                dot.classList.add('error');
                text.classList.add('error');
                text.textContent = 'Load failed: ' + msg;
            }
        }

        function rerenderAll() {
            renderBuilder();
            syncJsonFromNodes();
        }

        /* ============================================================
           EVENT WIRING
           ============================================================ */
        document.getElementById('btn-add-node').addEventListener('click', () => {
            nodes.push({
                id: '',
                title: '',
                selectionType: 'single',
                children: [],
                visibilityRule: null
            });
            rerenderAll();
        });

        // Load: fetch storage/app/product/rules.json from the server.
        document.getElementById('btn-load').addEventListener('click', async () => {
            if (nodes.length > 0 && !confirm('This replaces the current tree. Continue?')) return;
            updateStatus('loading', `Loading ${RULES_URL}…`);
            try {
                const res = await fetch(RULES_URL, {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error(`HTTP ${res.status} — ${res.statusText}`);
                const parsed = await res.json();
                if (!Array.isArray(parsed)) throw new Error('Top level must be an array of nodes.');
                nodes = parsed;
                rerenderAll();
            } catch (e) {
                updateStatus('load-error', e.message);
            }
        });

        document.getElementById('btn-clear').addEventListener('click', () => {
            if (nodes.length === 0) return;
            if (confirm('Clear all nodes?')) {
                nodes = [];
                rerenderAll();
            }
        });

        // Bulk expand/collapse helpers.
        document.getElementById('btn-collapse-all').addEventListener('click', () => {
            nodes.forEach(n => collapsedNodes.add(n));
            renderBuilder();
        });
        document.getElementById('btn-expand-all').addEventListener('click', () => {
            nodes.forEach(n => collapsedNodes.delete(n));
            renderBuilder();
        });

        document.getElementById('btn-apply').addEventListener('click', applyJsonFromTextarea);

        document.getElementById('btn-copy').addEventListener('click', async () => {
            const ta = document.getElementById('json-text');
            const btn = document.getElementById('btn-copy');
            const orig = btn.textContent;
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(ta.value);
                } else {
                    ta.select();
                    document.execCommand('copy');
                    ta.setSelectionRange(0, 0);
                }
                btn.textContent = '✓ Copied';
                setTimeout(() => { btn.textContent = orig; }, 1400);
            } catch (e) {
                btn.textContent = '✕ Failed';
                setTimeout(() => { btn.textContent = orig; }, 1400);
            }
        });

        document.getElementById('json-text').addEventListener('input', () => {
            textareaIsDirty = true;
            updateStatus('dirty');
        });

        // Ctrl/Cmd+Enter inside textarea = Apply
        document.getElementById('json-text').addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                applyJsonFromTextarea();
            }
        });

        /* ============================================================
           BOOT
           ============================================================ */
        rerenderAll();
    </script>
</body>

</html>