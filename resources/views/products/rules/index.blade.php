<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neural Filter — 3D Node Tree Search</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,700&family=JetBrains+Mono:wght@400;500&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg-deep: #050714;
            --bg-mid: #0a0f24;
            --ink-100: #e8ecff;
            --ink-200: #b8c0e0;
            --ink-300: #7a85b8;
            --ink-400: #4a5278;
            --line: rgba(122, 133, 184, 0.18);
            --line-strong: rgba(122, 133, 184, 0.35);
            --accent-cyan: #5eead4;
            --accent-magenta: #f472b6;
            --accent-amber: #fbbf24;
            --accent-violet: #a78bfa;
            --glow-cyan: rgba(94, 234, 212, 0.45);
            --glow-magenta: rgba(244, 114, 182, 0.5);
            --glow-amber: rgba(251, 191, 36, 0.55);
            --panel-bg: rgba(10, 15, 36, 0.72);
            --panel-border: rgba(94, 234, 212, 0.15);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            width: 100%;
            overflow: hidden;
            background: var(--bg-deep);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--ink-100);
            -webkit-font-smoothing: antialiased;
        }

        body {
            background:
                radial-gradient(ellipse at 50% 50%, var(--bg-mid), var(--bg-deep) 75%);
        }

        #stage { position: fixed; inset: 0; z-index: 0; }
        #canvas { display: block; width: 100%; height: 100%; cursor: grab; }
        #canvas:active { cursor: grabbing; }

        #labels { position: fixed; inset: 0; pointer-events: none; z-index: 5; }

        .lbl {
            position: absolute;
            transform: translate(-50%, -50%);
            pointer-events: auto;
            user-select: none;
            transition: opacity 0.18s ease;
            will-change: transform, opacity;
        }

        .lbl-main {
            text-align: center;
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: -0.01em;
            color: var(--ink-100);
            white-space: nowrap;
        }

        .lbl-main.clickable {
            cursor: pointer;
            transition: color 0.15s ease, opacity 0.18s ease;
        }
        .lbl-main.clickable:hover { color: var(--accent-cyan); }

        .lbl-main.collapsed::after {
            content: ' ▾';
            color: var(--accent-cyan);
            font-size: 11px;
            opacity: 0.85;
        }
        .lbl-main.expanded.has-selection::after {
            content: ' ▴';
            color: var(--accent-cyan);
            font-size: 11px;
            opacity: 0.85;
        }

        .lbl-child {
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 11.5px;
            font-weight: 500;
            color: var(--ink-200);
            background: rgba(10, 15, 36, 0.55);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            padding: 5px 11px;
            border: 1px solid var(--line);
            border-radius: 999px;
            white-space: nowrap;
            transition: color 0.15s ease, background 0.15s ease, border-color 0.15s ease, opacity 0.18s ease;
        }

        .lbl-child:hover {
            color: var(--ink-100);
            border-color: var(--accent-cyan);
        }

        .lbl-child.selected {
            color: var(--bg-deep);
            background: var(--accent-cyan);
            border-color: var(--accent-cyan);
            font-weight: 600;
        }

        /* ===== Brand mark ===== */
        #brand {
            position: fixed;
            top: 24px;
            left: 28px;
            z-index: 10;
            pointer-events: none;
        }

        #brand .tag {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.32em;
            color: var(--accent-cyan);
            text-transform: uppercase;
            margin-bottom: 2px;
            opacity: 0.7;
        }

        #brand .name {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 700;
            font-size: 22px;
            letter-spacing: -0.03em;
            color: var(--ink-100);
        }

        #brand .name em {
            font-style: italic;
            font-weight: 400;
            color: var(--accent-cyan);
        }

        /* ===== Side panel ===== */
        #panel {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 10;
            width: 320px;
            max-height: calc(100vh - 48px);
            overflow-y: auto;
            background: var(--panel-bg);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid var(--panel-border);
            border-radius: 14px;
            padding: 18px 18px 16px;
            font-size: 13px;
        }

        #panel::-webkit-scrollbar { width: 6px; }
        #panel::-webkit-scrollbar-track { background: transparent; }
        #panel::-webkit-scrollbar-thumb { background: var(--line-strong); border-radius: 3px; }

        .panel-title {
            font-family: 'JetBrains Mono', monospace;
            font-size: 9.5px;
            font-weight: 500;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: var(--accent-cyan);
            margin-bottom: 10px;
        }

        .panel-section + .panel-section {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
        }

        .filter-row { margin-bottom: 11px; }
        .filter-row:last-child { margin-bottom: 0; }

        .filter-name {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 600;
            font-size: 13.5px;
            color: var(--ink-100);
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-name .badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 8.5px;
            font-weight: 500;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--ink-300);
            padding: 2px 6px;
            border: 1px solid var(--line);
            border-radius: 3px;
        }

        .filter-values {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11.5px;
            color: var(--accent-cyan);
            line-height: 1.5;
        }

        .filter-values.empty { color: var(--ink-400); font-style: italic; }

        .json-block {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            line-height: 1.55;
            color: var(--ink-200);
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 10px 12px;
            overflow-x: auto;
            white-space: pre;
        }

        .json-block .k { color: var(--accent-cyan); }
        .json-block .s { color: var(--accent-magenta); }
        .json-block .n { color: var(--accent-amber); }

        /* Configuration section */
        .config-status {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            color: var(--ink-300);
            margin-bottom: 8px;
            padding: 5px 8px;
            background: rgba(0, 0, 0, 0.25);
            border-left: 2px solid var(--ink-400);
            border-radius: 3px;
        }
        .config-status.loaded { color: var(--accent-cyan); border-left-color: var(--accent-cyan); }
        .config-status.error { color: #f87171; border-left-color: #f87171; }
        .config-status.loading { color: var(--accent-amber); border-left-color: var(--accent-amber); }

        #config-input {
            width: 100%;
            min-height: 80px;
            max-height: 180px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 8px 10px;
            color: var(--ink-200);
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            line-height: 1.5;
            resize: vertical;
            outline: none;
            transition: border-color 0.15s ease;
            display: block;
        }
        #config-input:focus { border-color: var(--accent-cyan); }
        #config-input::placeholder { color: var(--ink-400); font-style: italic; }

        .config-actions {
            display: flex;
            gap: 6px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .cfg-btn {
            background: rgba(94, 234, 212, 0.05);
            border: 1px solid var(--line);
            color: var(--ink-200);
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 6px 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.15s ease;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .cfg-btn:hover {
            color: var(--accent-cyan);
            border-color: var(--accent-cyan);
        }
        .cfg-btn.primary {
            background: var(--accent-cyan);
            color: var(--bg-deep);
            border-color: var(--accent-cyan);
            font-weight: 600;
        }
        .cfg-btn.primary:hover {
            background: #4dd4be;
        }

        .config-error {
            display: none;
            margin-top: 8px;
            padding: 6px 9px;
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.35);
            border-radius: 4px;
            color: #f87171;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            line-height: 1.4;
        }
        .config-error.show { display: block; }

        /* Empty state on canvas */
        #empty-state {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 4;
            text-align: center;
            color: var(--ink-300);
            pointer-events: none;
            display: none;
            padding: 0 20px;
        }
        #empty-state.show { display: block; }
        #empty-state h2 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 600;
            font-size: 22px;
            color: var(--ink-100);
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        #empty-state p {
            font-size: 13px;
            line-height: 1.5;
            max-width: 360px;
            margin: 0 auto;
        }

        #hints {
            position: fixed;
            bottom: 22px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            display: flex;
            gap: 22px;
            align-items: center;
            padding: 9px 18px;
            background: rgba(10, 15, 36, 0.6);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--line);
            border-radius: 999px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            letter-spacing: 0.1em;
            color: var(--ink-300);
        }

        #hints .key {
            color: var(--ink-100);
            background: rgba(94, 234, 212, 0.08);
            border: 1px solid var(--line-strong);
            border-radius: 4px;
            padding: 2px 6px;
            margin-right: 6px;
            font-weight: 500;
        }

        #hints .item { display: flex; align-items: center; }

        #reset {
            position: fixed;
            bottom: 22px;
            right: 28px;
            z-index: 10;
            background: rgba(10, 15, 36, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid var(--line);
            color: var(--ink-200);
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            padding: 9px 16px;
            border-radius: 999px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        #reset:hover {
            color: var(--accent-magenta);
            border-color: var(--accent-magenta);
        }

        @media (max-width: 760px) {
            #panel { display: none; }
            #brand .name { font-size: 18px; }
            #hints { display: none; }
        }
    </style>
</head>

<body>

    <div id="brand">
        <div class="tag">Products - Neural Filter</div>
        <div class="name">Orthomaster <em>Testing</em></div>
    </div>

    <div id="empty-state">
        <h2>No configuration loaded</h2>
        <p>Paste your tree JSON in the side panel and click <strong>Load JSON</strong>, or click <strong>Load file</strong> to fetch from the server.</p>
    </div>

    <div id="stage">
        <canvas id="canvas"></canvas>
    </div>
    <div id="labels"></div>

    <aside id="panel">
        <div class="panel-section">
            <div class="panel-title">Configuration</div>
            <div class="config-status" id="config-status">No configuration loaded</div>
            <textarea id="config-input" placeholder='Paste your tree JSON here...&#10;[{ "id": "...", "title": "...", "children": [...], ... }]' spellcheck="false"></textarea>
            <div class="config-actions">
                <button class="cfg-btn primary" id="btn-load-json">Load JSON</button>
                <button class="cfg-btn" id="btn-load-file">Load file</button>
                <button class="cfg-btn" id="btn-clear-config">Clear</button>
            </div>
            <div class="config-error" id="config-error"></div>
        </div>
        <div class="panel-section">
            <div class="panel-title">Active filters</div>
            <div id="active-filters"></div>
        </div>
        <div class="panel-section">
            <div class="panel-title">State JSON</div>
            <div class="json-block" id="state-json"></div>
        </div>
    </aside>

    <div id="hints">
        <div class="item"><span class="key">DRAG</span>Rotate</div>
        <div class="item"><span class="key">WHEEL</span>Zoom</div>
        <div class="item"><span class="key">CLICK</span>Select node</div>
    </div>

    <button id="reset">Reset</button>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script>
        /* ============================================================
           1. CONFIG
           ============================================================ */
        let NODE_CONFIG = [];

        // URL endpoint that serves storage/app/product/rules.json
        // Laravel options:
        //   1) Symlink:  storage/app/public/product/rules.json + `php artisan storage:link`
        //                → accessible at /storage/product/rules.json
        //   2) Route:    Route::get('/product/rules.json',
        //                  fn() => response()->file(storage_path('app/product/rules.json')));
        //                → accessible at /product/rules.json
        // Adjust the path below to match your setup.
        const RULES_URL = "{{ route('rules.default') }}";

        /* ============================================================
           1b. AUTO LAYOUT
           ============================================================ */
        function getRuleRefs(rule, refs = new Set()) {
            if (!rule) return refs;
            if (rule.kind === "selected" || rule.kind === "any_selected") refs.add(rule.nodeId);
            else if (rule.rules) rule.rules.forEach(r => getRuleRefs(r, refs));
            else if (rule.rule) getRuleRefs(rule.rule, refs);
            return refs;
        }

        // Static, non-overlapping grid layout: same-depth nodes spread horizontally,
        // deeper nodes stacked vertically.
        function computeLayout(nodes) {
            if (nodes.length === 0) return new Map();

            const HORIZ = 9;    // horizontal spacing between same-depth siblings
            const VERT  = 5;    // vertical spacing between depth layers

            const depth = new Map();
            function getDepth(node, visiting = new Set()) {
                if (depth.has(node.id)) return depth.get(node.id);
                if (visiting.has(node.id)) return 0;
                visiting.add(node.id);
                if (!node.visibilityRule) { depth.set(node.id, 0); return 0; }
                let maxD = -1;
                getRuleRefs(node.visibilityRule).forEach(refId => {
                    const dep = nodes.find(n => n.id === refId);
                    if (dep) maxD = Math.max(maxD, getDepth(dep, visiting));
                });
                visiting.delete(node.id);
                const d = maxD + 1;
                depth.set(node.id, d);
                return d;
            }
            nodes.forEach(n => getDepth(n));

            const byDepth = new Map();
            nodes.forEach(n => {
                const d = depth.get(n.id);
                if (!byDepth.has(d)) byDepth.set(d, []);
                byDepth.get(d).push(n);
            });

            const positions = new Map();
            const sortedDepths = [...byDepth.keys()].sort((a, b) => a - b);

            for (const d of sortedDepths) {
                const layer = byDepth.get(d);
                const n = layer.length;
                layer.forEach((node, i) => {
                    const x = (i - (n - 1) / 2) * HORIZ;
                    const y = d * VERT;
                    positions.set(node.id, [x, y, 0]);
                });
            }

            return positions;
        }

        let LAYOUT = computeLayout(NODE_CONFIG);

        /* ============================================================
           2. STATE & LOGIC EVALUATOR
           ============================================================ */
        const state = { selections: {} };

        function evaluateRule(rule, selections) {
            if (!rule) return true;
            if (rule.kind === "selected") return (selections[rule.nodeId] || []).includes(rule.childId);
            if (rule.kind === "any_selected") return (selections[rule.nodeId] || []).length > 0;
            if (rule.op === "AND") return rule.rules.every(r => evaluateRule(r, selections));
            if (rule.op === "OR") return rule.rules.some(r => evaluateRule(r, selections));
            if (rule.op === "NOT") return !evaluateRule(rule.rule, selections);
            return false;
        }

        function isNodeVisible(node, selections) {
            if (!evaluateRule(node.visibilityRule, selections)) return false;
            if (node.visibilityRule) {
                return node.children.some(c => isChildVisible(c, selections));
            }
            return true;
        }
        function isChildVisible(child, selections) { return evaluateRule(child.visibilityRule, selections); }

        function pruneInvalidSelections() {
            let changed = true;
            while (changed) {
                changed = false;
                for (const nodeId of Object.keys(state.selections)) {
                    const node = NODE_CONFIG.find(n => n.id === nodeId);
                    if (!node || !isNodeVisible(node, state.selections)) {
                        delete state.selections[nodeId];
                        changed = true;
                        continue;
                    }
                    const filtered = state.selections[nodeId].filter(childId => {
                        const child = node.children.find(c => c.id === childId);
                        return child && isChildVisible(child, state.selections);
                    });
                    if (filtered.length !== state.selections[nodeId].length) {
                        if (filtered.length === 0) delete state.selections[nodeId];
                        else state.selections[nodeId] = filtered;
                        changed = true;
                    }
                }
            }
        }

        function inferConnections(visibleNodeIds) {
            const edges = [];
            for (const node of NODE_CONFIG) {
                if (!visibleNodeIds.has(node.id)) continue;
                const refs = new Set();
                getRuleRefs(node.visibilityRule, refs);
                node.children.forEach(child => getRuleRefs(child.visibilityRule, refs));
                refs.forEach(refId => {
                    if (refId !== node.id && visibleNodeIds.has(refId)) edges.push([refId, node.id]);
                });
            }
            return edges;
        }

        // Single-select: clicking a non-selected option selects + auto-collapses;
        //                clicking the already-selected option toggles expansion (does NOT deselect).
        // Multi-select : standard toggle behavior.
        function toggleSelection(nodeId, childId) {
            const node = NODE_CONFIG.find(n => n.id === nodeId);
            if (!node) return;
            const entry = sceneNodes.get(nodeId);
            const current = state.selections[nodeId] || [];
            const isSelected = current.includes(childId);

            if (node.selectionType === 'single') {
                if (isSelected) {
                    if (entry) entry.expanded = !entry.expanded;
                    // Selection unchanged: clicking selected only toggles expansion.
                } else {
                    state.selections[nodeId] = [childId];
                    if (entry) entry.expanded = false; // auto-collapse on new pick
                }
            } else {
                let next;
                if (isSelected) next = current.filter(c => c !== childId);
                else next = [...current, childId];
                if (next.length === 0) delete state.selections[nodeId];
                else state.selections[nodeId] = next;
            }
            pruneInvalidSelections();
        }

        // Clicking the main node title/sphere toggles expansion (single-select only).
        function toggleNodeExpansion(nodeId) {
            const node = NODE_CONFIG.find(n => n.id === nodeId);
            if (!node || node.selectionType !== 'single') return;
            const entry = sceneNodes.get(nodeId);
            if (!entry) return;
            const sels = state.selections[nodeId] || [];
            // No selection yet → already showing all options, nothing to toggle.
            if (sels.length === 0) return;
            entry.expanded = !entry.expanded;
        }

        /* ============================================================
           3. THREE.JS SCENE
           ============================================================ */
        const canvas = document.getElementById('canvas');
        const labelsEl = document.getElementById('labels');

        const scene = new THREE.Scene();
        scene.fog = new THREE.FogExp2(0x050714, 0.015);

        const camera = new THREE.PerspectiveCamera(50, window.innerWidth / window.innerHeight, 0.1, 200);
        camera.position.set(0, 2, 24);

        const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setClearColor(0x000000, 0);

        scene.add(new THREE.AmbientLight(0xffffff, 0.6));
        const keyLight = new THREE.PointLight(0xffffff, 0.5, 80);
        keyLight.position.set(0, 10, 15);
        scene.add(keyLight);

        /* ============================================================
           4. NODE BUILDERS
           ============================================================ */
        const COLOR_MAP = {
            cyan:    { core: 0x5eead4, halo: 0x5eead4 },
            magenta: { core: 0xf472b6, halo: 0xf472b6 },
            amber:   { core: 0xfbbf24, halo: 0xfbbf24 },
            violet:  { core: 0xa78bfa, halo: 0xa78bfa }
        };

        function buildMainNode(node) {
            const group = new THREE.Group();
            const pos = LAYOUT.get(node.id) || [0, 0, 0];
            group.position.set(...pos);
            group.userData = { type: 'main', nodeId: node.id, accent: node.accent || 'cyan' };

            const colors = COLOR_MAP[node.accent || 'cyan'];

            const core = new THREE.Mesh(
                new THREE.SphereGeometry(0.36, 24, 24),
                new THREE.MeshStandardMaterial({
                    color: colors.core,
                    emissive: colors.halo,
                    emissiveIntensity: 0.6,
                    roughness: 0.4,
                    metalness: 0
                })
            );
            // Mark for raycast: clicking main core toggles expansion.
            core.userData = { kind: 'main', nodeId: node.id };
            group.add(core);
            group.userData.core = core;
            return group;
        }

        function buildChildNode(parentNode, child, idx, total) {
            const group = new THREE.Group();
            // Wider fan + larger radius so labels don't crowd in expanded mode.
            const radius = 2.6;
            const spread = total === 1 ? 0 : Math.PI * 0.7;
            const angle = total === 1 ? 0 : (idx / (total - 1) - 0.5) * spread;
            group.position.set(
                Math.sin(angle) * radius,
                Math.cos(angle) * radius * 0.7,
                0
            );
            group.userData = {
                type: 'child', nodeId: parentNode.id, childId: child.id,
                label: child.label, accent: parentNode.accent || 'cyan'
            };

            const core = new THREE.Mesh(
                new THREE.SphereGeometry(0.13, 16, 16),
                new THREE.MeshStandardMaterial({
                    color: 0x1a2244,
                    emissive: 0x000000,
                    emissiveIntensity: 0,
                    roughness: 0.5,
                    metalness: 0.2
                })
            );
            core.userData = { kind: 'child', nodeId: parentNode.id, childId: child.id };
            group.add(core);
            group.userData.core = core;
            return group;
        }

        /* ============================================================
           5. SCENE STATE
           ============================================================ */
        const sceneNodes = new Map();
        const interEdges = new Map();
        const childEdges = new Map();

        function makeLine(from, to, color, opacity, dashed = false) {
            const geo = new THREE.BufferGeometry().setFromPoints([from.clone(), to.clone()]);
            const mat = dashed
                ? new THREE.LineDashedMaterial({ color, transparent: true, opacity, dashSize: 0.18, gapSize: 0.12 })
                : new THREE.LineBasicMaterial({ color, transparent: true, opacity });
            const line = new THREE.Line(geo, mat);
            if (dashed) line.computeLineDistances();
            return line;
        }

        function ensureMainNode(node) {
            if (sceneNodes.has(node.id)) return sceneNodes.get(node.id);
            const main = buildMainNode(node);
            scene.add(main);

            const childMap = new Map();
            node.children.forEach((child, i) => {
                const childGroup = buildChildNode(node, child, i, node.children.length);
                main.add(childGroup);
                childMap.set(child.id, childGroup);

                // Static dashed connector main → child.
                const line = makeLine(new THREE.Vector3(0, 0, 0), childGroup.position, COLOR_MAP[node.accent || 'cyan'].halo, 0.18, true);
                main.add(line);
                childGroup.userData.connectorLine = line;
            });

            // Static scale; no scale-in animation.
            main.scale.setScalar(1);

            const entry = {
                main,
                children: childMap,
                label: null,
                childLabels: new Map(),
                expanded: true   // default: all options visible
            };

            const mainLbl = document.createElement('div');
            mainLbl.className = 'lbl lbl-main';
            mainLbl.textContent = node.title;
            // Make main label clickable for single-select (toggle expansion).
            if (node.selectionType === 'single') {
                mainLbl.classList.add('clickable');
                mainLbl.addEventListener('click', () => {
                    toggleNodeExpansion(node.id);
                    render();
                });
            }
            labelsEl.appendChild(mainLbl);
            entry.label = mainLbl;

            node.children.forEach(child => {
                const cl = document.createElement('div');
                cl.className = 'lbl lbl-child';
                cl.textContent = child.label;
                cl.addEventListener('click', () => { toggleSelection(node.id, child.id); render(); });
                labelsEl.appendChild(cl);
                entry.childLabels.set(child.id, cl);
            });

            sceneNodes.set(node.id, entry);
            return entry;
        }

        function removeMainNode(nodeId) {
            const entry = sceneNodes.get(nodeId);
            if (!entry) return;
            scene.remove(entry.main);
            if (entry.label) entry.label.remove();
            entry.childLabels.forEach(l => l.remove());
            // Drop any childEdges that belong to this node so they don't leak.
            for (const key of [...childEdges.keys()]) {
                if (key.startsWith(nodeId + '|')) childEdges.delete(key);
            }
            sceneNodes.delete(nodeId);
        }

        /* ============================================================
           6. RENDER
           ============================================================ */
        function render() {
            const visibleIds = new Set();
            for (const node of NODE_CONFIG) {
                if (isNodeVisible(node, state.selections)) visibleIds.add(node.id);
            }

            for (const node of NODE_CONFIG) {
                if (visibleIds.has(node.id)) ensureMainNode(node);
            }
            for (const id of [...sceneNodes.keys()]) {
                if (!visibleIds.has(id)) removeMainNode(id);
            }

            // Inter-node edges (between main nodes).
            const edges = inferConnections(visibleIds);
            const wantedKeys = new Set(edges.map(([a, b]) => `${a}|${b}`));
            for (const key of [...interEdges.keys()]) {
                if (!wantedKeys.has(key)) {
                    scene.remove(interEdges.get(key));
                    interEdges.delete(key);
                }
            }
            edges.forEach(([fromId, toId]) => {
                const key = `${fromId}|${toId}`;
                if (interEdges.has(key)) return;
                const a = sceneNodes.get(fromId).main.position;
                const b = sceneNodes.get(toId).main.position;
                const line = makeLine(a, b, 0x7a85b8, 0.4, false);
                scene.add(line);
                interEdges.set(key, line);
            });

            // Per-node child rendering, including expansion logic for single-select.
            for (const [nodeId, entry] of sceneNodes) {
                const node = NODE_CONFIG.find(n => n.id === nodeId);
                const sels = state.selections[nodeId] || [];

                // Update main label class hints (collapsed/expanded indicators).
                if (node.selectionType === 'single') {
                    entry.label.classList.toggle('has-selection', sels.length > 0);
                    entry.label.classList.toggle('collapsed', sels.length > 0 && !entry.expanded);
                    entry.label.classList.toggle('expanded',   sels.length > 0 &&  entry.expanded);
                }

                entry.children.forEach((cg, childId) => {
                    const child = node.children.find(c => c.id === childId);
                    const childRuleVisible = isChildVisible(child, state.selections);

                    // Apply collapse logic: when a single-select node has a selection
                    // and is collapsed, only the selected child is rendered.
                    let visible = childRuleVisible;
                    if (visible && node.selectionType === 'single' && !entry.expanded && sels.length > 0) {
                        visible = sels.includes(childId);
                    }

                    cg.visible = visible;
                    if (cg.userData.connectorLine) cg.userData.connectorLine.visible = visible;

                    const lbl = entry.childLabels.get(childId);
                    lbl.style.pointerEvents = visible ? '' : 'none';

                    const selected = sels.includes(childId);
                    const coreMat = cg.userData.core.material;
                    const colors = COLOR_MAP[node.accent || 'cyan'];
                    if (selected) {
                        coreMat.color.setHex(colors.core);
                        coreMat.emissive.setHex(colors.halo);
                        coreMat.emissiveIntensity = 0.7;
                    } else {
                        coreMat.color.setHex(0x1a2244);
                        coreMat.emissive.setHex(0x000000);
                        coreMat.emissiveIntensity = 0;
                    }
                    lbl.classList.toggle('selected', selected);
                });
            }

            // Highlighted edges from main → currently-selected children.
            const wantedChild = new Set();
            for (const [nodeId, entry] of sceneNodes) {
                const sels = state.selections[nodeId] || [];
                sels.forEach(childId => {
                    const key = `${nodeId}|${childId}`;
                    wantedChild.add(key);
                    if (childEdges.has(key)) return;
                    const cg = entry.children.get(childId);
                    if (!cg) return;
                    const node = NODE_CONFIG.find(n => n.id === nodeId);
                    const colors = COLOR_MAP[node.accent || 'cyan'];
                    const line = makeLine(new THREE.Vector3(0, 0, 0), cg.position, colors.halo, 0.75, false);
                    entry.main.add(line);
                    childEdges.set(key, line);
                });
            }
            for (const key of [...childEdges.keys()]) {
                if (!wantedChild.has(key)) {
                    const line = childEdges.get(key);
                    if (line && line.parent) line.parent.remove(line);
                    childEdges.delete(key);
                }
            }

            updateSidePanel();
        }

        /* ============================================================
           7. SIDE PANEL
           ============================================================ */
        function updateSidePanel() {
            const visibleIds = NODE_CONFIG.filter(n => isNodeVisible(n, state.selections)).map(n => n.id);
            const filtersEl = document.getElementById('active-filters');
            filtersEl.innerHTML = visibleIds.map(id => {
                const node = NODE_CONFIG.find(n => n.id === id);
                const sels = state.selections[id] || [];
                const labels = sels.map(s => node.children.find(c => c.id === s)?.label).filter(Boolean);
                return `<div class="filter-row">
      <div class="filter-name">${node.title} <span class="badge">${node.selectionType === 'single' ? 'SINGLE' : 'MULTI'}</span></div>
      <div class="filter-values ${labels.length === 0 ? 'empty' : ''}">${labels.length === 0 ? 'not selected' : '› ' + labels.join('  ›  ')}</div>
    </div>`;
            }).join('');

            const json = JSON.stringify(state.selections, null, 2)
                .replace(/"([^"]+)":/g, '<span class="k">"$1"</span>:')
                .replace(/: "([^"]+)"/g, ': <span class="s">"$1"</span>')
                .replace(/\[\s*\]/g, '[]');
            document.getElementById('state-json').innerHTML = json || '{}';
        }

        /* ============================================================
           8. CONTROLS
           ============================================================ */
        const controls = {
            azimuth: 0, polar: Math.PI / 2 - 0.05,
            distance: 24, target: new THREE.Vector3(0, 6, 0),
            isDragging: false, lastX: 0, lastY: 0
        };

        function applyCamera() {
            const x = controls.distance * Math.sin(controls.polar) * Math.sin(controls.azimuth);
            const y = controls.distance * Math.cos(controls.polar);
            const z = controls.distance * Math.sin(controls.polar) * Math.cos(controls.azimuth);
            camera.position.set(controls.target.x + x, controls.target.y + y, controls.target.z + z);
            camera.lookAt(controls.target);
        }

        // Auto-fit camera distance / target to current LAYOUT bounds.
        function fitCamera() {
            if (LAYOUT.size === 0) {
                controls.distance = 18;
                controls.target.set(0, 1, 0);
                applyCamera();
                return;
            }
            let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
            for (const pos of LAYOUT.values()) {
                minX = Math.min(minX, pos[0]); maxX = Math.max(maxX, pos[0]);
                minY = Math.min(minY, pos[1]); maxY = Math.max(maxY, pos[1]);
            }
            const cx = (minX + maxX) / 2;
            const cy = (minY + maxY) / 2;
            // Padding to account for child fan around outermost mains.
            const w = (maxX - minX) + 10;
            const h = (maxY - minY) + 8;
            const fov = camera.fov * Math.PI / 180;
            const aspect = window.innerWidth / window.innerHeight;
            const distH = (h / 2) / Math.tan(fov / 2);
            const distW = (w / 2) / Math.tan(fov / 2) / aspect;
            controls.distance = Math.max(14, Math.min(60, Math.max(distH, distW)));
            controls.target.set(cx, cy, 0);
            applyCamera();
        }

        applyCamera();

        canvas.addEventListener('mousedown', (e) => {
            controls.isDragging = true;
            controls.lastX = e.clientX;
            controls.lastY = e.clientY;
        });
        window.addEventListener('mouseup', () => { controls.isDragging = false; });
        window.addEventListener('mousemove', (e) => {
            if (!controls.isDragging) return;
            const dx = e.clientX - controls.lastX;
            const dy = e.clientY - controls.lastY;
            controls.azimuth -= dx * 0.005;
            controls.polar = Math.max(0.3, Math.min(Math.PI - 0.3, controls.polar - dy * 0.005));
            controls.lastX = e.clientX;
            controls.lastY = e.clientY;
            applyCamera();
        });

        canvas.addEventListener('wheel', (e) => {
            e.preventDefault();
            controls.distance = Math.max(8, Math.min(60, controls.distance + e.deltaY * 0.015));
            applyCamera();
        }, { passive: false });

        let touchStart = null, lastDistance = null;
        canvas.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1) {
                touchStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
            } else if (e.touches.length === 2) {
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                lastDistance = Math.sqrt(dx * dx + dy * dy);
            }
        });
        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            if (e.touches.length === 1 && touchStart) {
                const dx = e.touches[0].clientX - touchStart.x;
                const dy = e.touches[0].clientY - touchStart.y;
                controls.azimuth -= dx * 0.006;
                controls.polar = Math.max(0.3, Math.min(Math.PI - 0.3, controls.polar - dy * 0.006));
                touchStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
                applyCamera();
            } else if (e.touches.length === 2) {
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                const d = Math.sqrt(dx * dx + dy * dy);
                if (lastDistance) {
                    controls.distance = Math.max(8, Math.min(60, controls.distance - (d - lastDistance) * 0.05));
                    applyCamera();
                }
                lastDistance = d;
            }
        }, { passive: false });
        canvas.addEventListener('touchend', () => { touchStart = null; lastDistance = null; });

        /* ============================================================
           9. PROJECT LABELS
           ============================================================ */
        const tempVec = new THREE.Vector3();

        function projectLabels() {
            for (const [, entry] of sceneNodes) {
                entry.main.getWorldPosition(tempVec);
                tempVec.y -= 0.95;
                tempVec.project(camera);
                const x = (tempVec.x * 0.5 + 0.5) * window.innerWidth;
                const y = (-tempVec.y * 0.5 + 0.5) * window.innerHeight;
                const cameraOK = tempVec.z < 1;
                entry.label.style.left = x + 'px';
                entry.label.style.top = y + 'px';
                entry.label.style.opacity = cameraOK ? '1' : '0';

                entry.children.forEach((cg, childId) => {
                    cg.getWorldPosition(tempVec);
                    tempVec.y -= 0.32;
                    tempVec.project(camera);
                    const cx = (tempVec.x * 0.5 + 0.5) * window.innerWidth;
                    const cy = (-tempVec.y * 0.5 + 0.5) * window.innerHeight;
                    const lbl = entry.childLabels.get(childId);
                    lbl.style.left = cx + 'px';
                    lbl.style.top = cy + 'px';
                    lbl.style.opacity = (cg.visible && tempVec.z < 1) ? '1' : '0';
                });
            }
        }

        /* ============================================================
           10. ANIMATION LOOP — NO motion, just label projection + render
           ============================================================ */
        function tick() {
            projectLabels();
            renderer.render(scene, camera);
            requestAnimationFrame(tick);
        }

        /* ============================================================
           11. RAYCAST
           ============================================================ */
        const raycaster = new THREE.Raycaster();
        const mouse = new THREE.Vector2();
        let dragMoved = false;

        canvas.addEventListener('mousedown', () => { dragMoved = false; });
        canvas.addEventListener('mousemove', () => { if (controls.isDragging) dragMoved = true; });
        canvas.addEventListener('click', (e) => {
            if (dragMoved) return;
            const rect = canvas.getBoundingClientRect();
            mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
            raycaster.setFromCamera(mouse, camera);

            // Pick from main cores + visible child cores.
            const meshes = [];
            for (const [, entry] of sceneNodes) {
                meshes.push(entry.main.userData.core);
                entry.children.forEach(cg => {
                    if (cg.visible) meshes.push(cg.userData.core);
                });
            }
            const hits = raycaster.intersectObjects(meshes, false);
            if (hits.length > 0) {
                const obj = hits[0].object;
                if (obj.userData.kind === 'main') {
                    toggleNodeExpansion(obj.userData.nodeId);
                } else if (obj.userData.kind === 'child') {
                    toggleSelection(obj.userData.nodeId, obj.userData.childId);
                }
                render();
            }
        });

        /* ============================================================
           12. RESET & RESIZE
           ============================================================ */
        document.getElementById('reset').addEventListener('click', () => {
            state.selections = {};
            // Reset expansion state for all current entries.
            for (const [, entry] of sceneNodes) entry.expanded = true;
            render();
        });

        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });

        /* ============================================================
           13. CONFIG LOADER
           ============================================================ */
        const STORAGE_KEY = 'neural_filter_config_v1';
        const cfgInput = document.getElementById('config-input');
        const cfgStatus = document.getElementById('config-status');
        const cfgError = document.getElementById('config-error');
        const emptyState = document.getElementById('empty-state');

        function setStatus(msg, kind) {
            cfgStatus.textContent = msg;
            cfgStatus.className = 'config-status' + (kind ? ' ' + kind : '');
        }

        function showError(msg) {
            cfgError.textContent = msg;
            cfgError.classList.add('show');
        }
        function hideError() {
            cfgError.classList.remove('show');
        }

        function validateConfig(cfg) {
            if (!Array.isArray(cfg)) throw new Error('Top level must be an array of nodes.');
            cfg.forEach((n, i) => {
                if (!n || typeof n !== 'object') throw new Error(`Node ${i} is not an object`);
                if (typeof n.id !== 'string' || !n.id) throw new Error(`Node ${i} missing valid id`);
                if (!Array.isArray(n.children)) throw new Error(`Node "${n.id}" missing children array`);
            });
        }

        function clearScene() {
            for (const id of [...sceneNodes.keys()]) removeMainNode(id);
            for (const [, line] of interEdges) scene.remove(line);
            interEdges.clear();
            childEdges.clear();
            state.selections = {};
        }

        function applyConfig(newConfig) {
            validateConfig(newConfig);
            clearScene();
            NODE_CONFIG = newConfig;
            LAYOUT = computeLayout(NODE_CONFIG);
            hideError();
            render();
            updateEmptyState();
            fitCamera();
            const count = NODE_CONFIG.length;
            setStatus(`Loaded ${count} node${count === 1 ? '' : 's'}`, 'loaded');
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(newConfig)); } catch (e) { /* ignore */ }
        }

        function updateEmptyState() {
            emptyState.classList.toggle('show', NODE_CONFIG.length === 0);
        }

        function loadFromTextarea() {
            try {
                const text = cfgInput.value.trim();
                if (!text) throw new Error('Textarea is empty');
                applyConfig(JSON.parse(text));
            } catch (e) {
                showError(e.message);
                setStatus('Load failed', 'error');
            }
        }

        document.getElementById('btn-load-json').addEventListener('click', loadFromTextarea);

        // Load file: fetch the JSON from the server (Laravel serves storage/app/product/rules.json).
        document.getElementById('btn-load-file').addEventListener('click', async () => {
            setStatus('Loading…', 'loading');
            hideError();
            try {
                const res = await fetch(RULES_URL, { cache: 'no-store', headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(`HTTP ${res.status} — ${res.statusText}`);
                const cfg = await res.json();
                cfgInput.value = JSON.stringify(cfg, null, 2);
                applyConfig(cfg);
            } catch (err) {
                showError(`Failed to load ${RULES_URL}: ${err.message}`);
                setStatus('Load failed', 'error');
            }
        });

        document.getElementById('btn-clear-config').addEventListener('click', () => {
            clearScene();
            NODE_CONFIG = [];
            LAYOUT = new Map();
            cfgInput.value = '';
            hideError();
            render();
            updateEmptyState();
            fitCamera();
            setStatus('No configuration loaded');
            try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
        });

        // Ctrl/Cmd + Enter inside the textarea = Load JSON
        cfgInput.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                loadFromTextarea();
            }
        });

        /* ============================================================
           14. BOOT — try restoring last config from localStorage
           ============================================================ */
        (function boot() {
            let restored = false;
            try {
                const saved = localStorage.getItem(STORAGE_KEY);
                if (saved) {
                    const cfg = JSON.parse(saved);
                    cfgInput.value = JSON.stringify(cfg, null, 2);
                    applyConfig(cfg);
                    restored = true;
                }
            } catch (e) { /* ignore */ }

            if (!restored) {
                render();
                updateEmptyState();
                fitCamera();
                setStatus('No configuration loaded');
            }
            tick();
        })();
    </script>
</body>

</html>