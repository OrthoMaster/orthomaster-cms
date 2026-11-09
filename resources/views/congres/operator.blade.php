<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    {{-- Pas de zoom : un double tap ou un pincement sur tablette déplacerait l'écran pendant la séance. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="robots" content="noindex">
    <title>Intervenants · {{ $board->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- Scène : largeur fixe, hauteur suivant l'écran (tablette 4:3, portable 16:10, projecteur 16:9) --- */
        html, body { margin: 0; height: 100%; overflow: hidden; background: #14224A; overscroll-behavior: none; }
        #stage {
            position: absolute; left: 0; top: 0; transform-origin: 0 0; overflow: hidden;
            color: #fff; background: #14224A; font-family: Poppins, Arial, sans-serif;
            -webkit-user-select: none; user-select: none; -webkit-touch-callout: none; -webkit-tap-highlight-color: transparent;
        }

        /* --- Tableau --- */
        #zones { position: absolute; inset: 28px; display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 24px; }
        .zone { min-width: 0; min-height: 0; display: flex; flex-direction: column; gap: 12px; }
        .zone-head { flex: none; height: 56px; display: flex; align-items: center; }
        .zone-name {
            display: flex; align-items: center; gap: 12px; max-width: 100%; min-height: 56px; padding: 0 10px 0 22px;
            border: 0; border-radius: 999px; color: #1B1F2A; font: inherit; font-size: 26px; font-weight: 600; cursor: pointer;
        }
        .zone-name .label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .zone-name .count { flex: none; padding: 2px 14px; border-radius: 999px; background: rgba(20,34,74,.85); color: #fff; font-size: 22px; line-height: 34px; }
        .zone-notes { flex: 1; min-height: 0; display: grid; gap: 14px; }

        /* --- Note --- */
        .slot { position: relative; min-width: 0; min-height: 0; isolation: isolate; cursor: grab; touch-action: none; }
        .slot::before, .slot::after {
            content: ''; position: absolute; inset: 0; border-radius: 6px; background: var(--color);
            box-shadow: 0 6px 12px rgba(0,0,0,.3); opacity: 0; transition: opacity 800ms ease;
        }
        .slot::before { z-index: 1; filter: brightness(.86); transform: translate(7px, 7px) rotate(-1deg); }
        .slot::after { z-index: 0; filter: brightness(.7); transform: translate(14px, 14px) rotate(1.5deg); }
        .slot.stack::before, .slot.stack::after { opacity: 1; }
        .more {
            position: absolute; left: 16px; bottom: -16px; z-index: 3; padding: 2px 16px; border-radius: 999px;
            background: #fff; color: #14224A; font-size: 24px; font-weight: 700; line-height: 32px;
            opacity: 0; transition: opacity 800ms ease;
        }
        .slot.stack .more { opacity: 1; }
        .slot.stack .note span { max-width: calc(100% - 96px); }

        .note {
            position: absolute; inset: 0; z-index: 2; box-sizing: border-box; padding: 16px 18px 10px;
            color: #1B1F2A; border-radius: 6px; box-shadow: 0 8px 18px rgba(0,0,0,.35);
            display: flex; flex-direction: column; justify-content: space-between; gap: 4px;
            transform: rotate(var(--tilt)); transition: transform 200ms ease, box-shadow 200ms ease;
        }
        .note p { flex: 1; min-height: 0; margin: 0; font-weight: 500; overflow: hidden; overflow-wrap: anywhere; }
        .note span { flex: none; align-self: flex-end; max-width: 100%; color: #3B4150; font-size: 17px; font-weight: 500; line-height: 22px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* --- Appui long, glisser --- */
        .slot.editing { z-index: 5; }
        .slot.editing .note { transform: scale(1.03); box-shadow: 0 0 0 5px #fff, 0 16px 34px rgba(0,0,0,.5); }
        .slot.dragging { z-index: 20; pointer-events: none; }
        .slot.target .note { box-shadow: 0 0 0 6px #FFC800, 0 8px 18px rgba(0,0,0,.35); }
        .remove {
            position: absolute; top: -22px; right: -22px; z-index: 6; display: none; width: 72px; height: 72px; padding: 0;
            border: 4px solid #fff; border-radius: 50%; background: #E53935; color: #fff; font: inherit; font-size: 44px; line-height: 60px; cursor: pointer;
            box-shadow: 0 6px 16px rgba(0,0,0,.4);
        }
        .editing > .remove, .editing .remove { display: block; }

        /* --- Liste complète d'un thème --- */
        #sheet { position: absolute; inset: 0; z-index: 50; display: none; background: rgba(8,14,32,.72); }
        #sheet.on { display: block; }
        .panel {
            position: absolute; left: 50%; top: 40px; bottom: 40px; width: min(1200px, calc(100% - 80px)); transform: translateX(-50%);
            display: flex; flex-direction: column; border-radius: 24px; background: #F4F6FA; color: #1B1F2A; overflow: hidden;
        }
        .panel-head { flex: none; display: flex; align-items: center; gap: 20px; padding: 24px 24px 18px 32px; background: #fff; }
        .panel-name { min-width: 0; padding: 8px 26px; border-radius: 999px; font-size: 30px; font-weight: 600; line-height: 40px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .panel-count { flex: none; color: #5B6270; font-size: 24px; font-weight: 500; }
        .panel-hint { flex: 1; min-width: 0; color: #5B6270; font-size: 20px; text-align: right; }
        .close { flex: none; width: 76px; height: 76px; padding: 0; border: 0; border-radius: 50%; background: #E3E6EC; color: #1B1F2A; font: inherit; font-size: 48px; line-height: 76px; cursor: pointer; }
        #sheet-list { flex: 1; min-height: 0; overflow-y: auto; padding: 20px 32px 40px; touch-action: pan-y; -webkit-overflow-scrolling: touch; }
        .item {
            position: relative; display: flex; gap: 20px; align-items: flex-start; margin-bottom: 14px; padding: 18px 96px 18px 20px;
            border-radius: 10px; background: var(--color); box-shadow: 0 2px 6px rgba(0,0,0,.12); cursor: grab;
            transition: box-shadow 200ms ease, transform 200ms ease;
        }
        .item.off { background: #fff; box-shadow: inset 8px 0 0 var(--color), 0 2px 6px rgba(0,0,0,.08); }
        .item-rank { flex: none; width: 56px; color: #3B4150; font-size: 24px; font-weight: 700; line-height: 36px; }
        .item-body { min-width: 0; flex: 1; }
        .item-text { font-size: 28px; font-weight: 500; line-height: 38px; overflow-wrap: anywhere; }
        .item-author { margin-top: 4px; color: #3B4150; font-size: 20px; }
        .item .remove { top: 50%; right: 14px; margin-top: -36px; }
        .item.editing { transform: scale(1.01); box-shadow: 0 0 0 4px #1D3470, 0 12px 28px rgba(0,0,0,.25); }
        .item.dragging { z-index: 5; }
        .divider { margin: 26px 0 18px; padding-top: 10px; border-top: 3px dashed #AEB5C2; color: #5B6270; font-size: 20px; font-weight: 600; }
        .sheet-empty { padding: 40px 0; color: #5B6270; font-size: 26px; text-align: center; }

        /* --- États --- */
        #empty { position: absolute; inset: 0; display: none; flex-direction: column; align-items: center; justify-content: center; gap: 22px; padding: 0 120px; text-align: center; }
        #empty.on { display: flex; }
        #empty .congress { color: #FFAF32; font-size: 30px; font-weight: 600; }
        #empty h1 { margin: 0; font-size: 64px; line-height: 76px; font-weight: 700; overflow-wrap: anywhere; }
        #empty .sub { font-size: 34px; font-weight: 500; }
        #empty .hint { max-width: 1100px; margin-top: 30px; color: #D5DDF0; font-size: 24px; line-height: 34px; }
        .brand { display: flex; align-items: center; gap: 10px; color: #EB7B0F; font-size: 26px; font-weight: 400; }
        .brand img { width: 40px; height: 40px; border-radius: 8px; object-fit: contain; }

        #status { position: absolute; right: 28px; bottom: 20px; z-index: 40; display: none; padding: 6px 20px; border-radius: 999px; background: #FFC800; color: #1B1F2A; font-size: 22px; font-weight: 600; }
        #status.on { display: block; }
        #offline { position: absolute; left: 16px; bottom: 12px; z-index: 40; width: 14px; height: 14px; border-radius: 50%; background: #FF4B4B; display: none; }
        #gone { position: absolute; inset: 0; z-index: 60; display: none; align-items: center; justify-content: center; background: #14224A; font-size: 64px; font-weight: 700; }
        #gone.on { display: flex; }
    </style>
</head>
<body>
<div id="stage">
    <div id="zones"></div>

    <div id="empty">
        @include('congres._brand')
        @if ($board->congress_name)
            <div class="congress">{{ $board->congress_name }}</div>
        @endif
        <h1>{{ $board->title }}</h1>
        <div class="sub">Aucune question pour le moment</div>
        <div class="hint">
            Appui long sur une question : la supprimer, ou la glisser sur une autre pour les échanger.<br>
            Touchez le nom d'un thème pour voir toutes ses questions.
        </div>
    </div>

    <div id="sheet" role="dialog" aria-modal="true" aria-labelledby="sheet-name">
        <div class="panel">
            <div class="panel-head">
                <div class="panel-name" id="sheet-name"></div>
                <div class="panel-count" id="sheet-count"></div>
                <div class="panel-hint">Appui long : déplacer ou supprimer</div>
                <button type="button" class="close" id="sheet-close" aria-label="Fermer">&times;</button>
            </div>
            <div id="sheet-list"></div>
        </div>
    </div>

    <div id="status"></div>
    <div id="offline"></div>
    <div id="gone">Code désactivé</div>
</div>

<script>
(function () {
    const API = @json('/api/congres/operator/' . $board->operator_code);
    const POLL_MS = 2000;
    const MIN_FONT_PX = 18;
    const LONG_PRESS_MS = 450;
    // Au-delà, le doigt fait défiler la liste au lieu de saisir la carte.
    const MOVE_TOLERANCE_PX = 10;
    // Les navigateurs tactiles rejouent un clic souris après le toucher : on l'ignore.
    const GHOST_MOUSE_MS = 800;
    const ENTER_MS = 900;
    const MOVE_MS = 450;
    const LEAVE_MS = 350;
    const SCROLL_EDGE_PX = 80;
    const SCROLL_STEP_PX = 14;
    const STATUS = { draft: 'Séance non démarrée', closed: 'Séance terminée' };

    const stage = document.getElementById('stage');
    const zonesEl = document.getElementById('zones');
    const emptyEl = document.getElementById('empty');
    const sheet = document.getElementById('sheet');
    const sheetList = document.getElementById('sheet-list');

    let revision = '';
    let themes = [];
    let firstPaint = true;
    let scale = 1;
    let pending = null;
    let busy = false;
    let sheetThemeId = null;
    let pollTimer = null;
    let inflight = false;
    let again = false;
    // Actions envoyées sans réponse : une lecture partie avant elles remettrait l'ancien ordre.
    let actions = 0;

    /* ===================== SCÈNE ===================== */

    function fit() {
        const landscape = window.innerWidth >= window.innerHeight;
        const width = landscape ? 1920 : 1200;
        scale = window.innerWidth / width;
        stage.style.width = width + 'px';
        stage.style.height = Math.round(window.innerHeight / scale) + 'px';
        stage.style.transform = 'scale(' + scale + ')';
    }
    window.addEventListener('resize', function () {
        fit();
        zonesEl.querySelectorAll('.note p').forEach(shrinkToFit);
    });
    fit();

    /* ===================== NOTES ===================== */

    function typeFor(length, tall) {
        if (tall) {
            if (length <= 60) return ['34px', '43px'];
            if (length <= 90) return ['30px', '38px'];
            return ['26px', '33px'];
        }
        if (length <= 60) return ['30px', '38px'];
        if (length <= 90) return ['26px', '33px'];
        return ['23px', '29px'];
    }

    function setType(element, content, theme) {
        const type = typeFor(content.length, theme.layout.rows === 3);
        element.style.fontSize = type[0];
        element.style.lineHeight = type[1];
    }

    function shrinkToFit(text) {
        let size = parseFloat(text.style.fontSize);
        while (text.scrollHeight > text.clientHeight && size > MIN_FONT_PX) {
            size -= 1;
            text.style.fontSize = size + 'px';
            text.style.lineHeight = Math.round(size * 1.27) + 'px';
        }
    }

    function removeButton() {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'remove';
        button.setAttribute('aria-label', 'Supprimer la question');
        button.innerHTML = '&times;';
        return button;
    }

    function buildSlot(question, theme) {
        const slot = document.createElement('div');
        slot.className = 'slot';
        slot.dataset.id = question.id;
        slot.style.setProperty('--color', theme.color);

        const note = document.createElement('article');
        note.className = 'note';
        note.style.background = theme.color;
        note.style.setProperty('--tilt', (((question.id * 37) % 5) - 2) * 0.6 + 'deg');

        const text = document.createElement('p');
        setType(text, question.text, theme);
        text.textContent = question.text;

        const author = document.createElement('span');
        author.textContent = question.author;

        const more = document.createElement('div');
        more.className = 'more';

        note.append(text, author);
        slot.append(note, more, removeButton());
        return slot;
    }

    function zoneFor(theme) {
        let zone = zonesEl.querySelector('.zone[data-theme="' + theme.id + '"]');
        if (!zone) {
            zone = document.createElement('section');
            zone.className = 'zone';
            zone.dataset.theme = theme.id;
            zone.innerHTML = '<div class="zone-head"><button type="button" class="zone-name"><span class="label"></span><span class="count"></span></button></div><div class="zone-notes"></div>';
        }
        return zone;
    }

    function syncChildren(parent, wanted) {
        wanted.forEach(function (child, index) {
            if (parent.children[index] !== child) {
                parent.insertBefore(child, parent.children[index] || null);
            }
        });
        while (parent.children.length > wanted.length) {
            parent.lastElementChild.remove();
        }
    }

    function positions(root, selector) {
        const rects = new Map();
        root.querySelectorAll(selector).forEach(function (el) {
            rects.set(el, el.getBoundingClientRect());
        });
        return rects;
    }

    function glideFrom(rects) {
        rects.forEach(function (before, el) {
            if (!el.isConnected) {
                return;
            }
            const after = el.getBoundingClientRect();
            const dx = (before.left - after.left) / scale;
            const dy = (before.top - after.top) / scale;
            if (Math.abs(dx) > 1 || Math.abs(dy) > 1) {
                el.animate(
                    [{ transform: 'translate(' + dx + 'px, ' + dy + 'px)' }, { transform: 'none' }],
                    { duration: MOVE_MS, easing: 'cubic-bezier(.4, 0, .2, 1)' }
                );
            }
        });
    }

    function appear(slot) {
        slot.animate({ opacity: [0, 1] }, { duration: ENTER_MS, easing: 'ease-out' });
    }

    function markStack(notes, theme, shown) {
        const slots = Array.from(notes.children);
        const hidden = theme.total - shown;
        slots.forEach(function (slot, index) {
            const last = hidden > 0 && index === slots.length - 1;
            slot.classList.toggle('stack', last);
            if (last) {
                slot.querySelector('.more').textContent = '+' + hidden;
            }
        });
    }

    function visibleThemes() {
        return themes.filter(function (theme) { return theme.total > 0; });
    }

    function paintBoard() {
        const shownThemes = visibleThemes();
        const rects = positions(zonesEl, '.slot, .zone-head');
        const entering = [];

        emptyEl.classList.toggle('on', shownThemes.length === 0);
        zonesEl.style.gridTemplateRows = shownThemes.length >= 4 ? 'repeat(2, minmax(0, 1fr))' : 'minmax(0, 1fr)';

        const zones = shownThemes.map(function (theme) {
            const zone = zoneFor(theme);
            zone.style.gridColumn = 'span ' + theme.layout.span;

            const name = zone.querySelector('.zone-name');
            name.style.background = theme.color;
            name.querySelector('.label').textContent = theme.name;
            name.querySelector('.count').textContent = theme.total;
            name.setAttribute('aria-label', theme.name + ' : voir les ' + theme.total + ' questions');

            const notes = zone.querySelector('.zone-notes');
            notes.style.gridTemplateColumns = 'repeat(' + theme.layout.cols + ', minmax(0, 1fr))';
            notes.style.gridTemplateRows = 'repeat(' + theme.layout.rows + ', minmax(0, 1fr))';

            const current = {};
            Array.from(notes.children).forEach(function (slot) { current[slot.dataset.id] = slot; });
            const visible = theme.questions.slice(0, theme.layout.capacity);
            const wanted = visible.map(function (question) {
                const slot = current[question.id];
                if (!slot) {
                    const created = buildSlot(question, theme);
                    entering.push(created);
                    return created;
                }
                setType(slot.querySelector('p'), question.text, theme);
                return slot;
            });
            syncChildren(notes, wanted);
            markStack(notes, theme, visible.length);
            return zone;
        });

        syncChildren(zonesEl, zones);
        zonesEl.querySelectorAll('.note p').forEach(shrinkToFit);

        if (!firstPaint) {
            glideFrom(rects);
            entering.forEach(appear);
        }
        firstPaint = false;
    }

    /* ===================== LISTE D'UN THÈME ===================== */

    function sheetTheme() {
        return themes.find(function (theme) { return theme.id === sheetThemeId; });
    }

    function buildItem(question, index, theme) {
        const item = document.createElement('div');
        item.className = 'item';
        item.dataset.id = question.id;
        item.style.setProperty('--color', theme.color);
        item.innerHTML = '<div class="item-rank"></div><div class="item-body"><div class="item-text"></div><div class="item-author"></div></div>';
        item.querySelector('.item-text').textContent = question.text;
        item.querySelector('.item-author').textContent = question.author;
        item.append(removeButton());
        return item;
    }

    // Numéros, couleur pleine pour les questions à l'écran et séparateur, recalculés après chaque déplacement.
    function renumber() {
        const theme = sheetTheme();
        if (!theme) {
            return;
        }
        const items = Array.from(sheetList.querySelectorAll('.item'));
        const divider = sheetList.querySelector('.divider');
        items.forEach(function (item, index) {
            item.querySelector('.item-rank').textContent = index + 1;
            item.classList.toggle('off', index >= theme.layout.capacity);
        });
        if (divider) {
            const first = items[theme.layout.capacity];
            if (first) {
                sheetList.insertBefore(divider, first);
                divider.style.display = '';
            } else {
                divider.style.display = 'none';
            }
        }
    }

    function paintSheet() {
        const theme = sheetTheme();
        if (!theme) {
            closeSheet();
            return;
        }
        const name = document.getElementById('sheet-name');
        name.textContent = theme.name;
        name.style.background = theme.color;
        document.getElementById('sheet-count').textContent = theme.total + (theme.total > 1 ? ' questions' : ' question');

        const scroll = sheetList.scrollTop;
        const editing = sheetList.querySelector('.item.editing');
        const editingId = editing ? editing.dataset.id : null;

        sheetList.replaceChildren();
        if (theme.questions.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'sheet-empty';
            empty.textContent = 'Aucune question dans ce thème.';
            sheetList.append(empty);
            return;
        }
        theme.questions.forEach(function (question, index) {
            const item = buildItem(question, index, theme);
            if (String(question.id) === editingId) {
                item.classList.add('editing');
            }
            sheetList.append(item);
        });
        const divider = document.createElement('div');
        divider.className = 'divider';
        divider.textContent = 'Hors écran';
        sheetList.append(divider);
        renumber();
        sheetList.scrollTop = scroll;
    }

    function openSheet(themeId) {
        clearEditing();
        sheetThemeId = themeId;
        sheet.classList.add('on');
        sheetList.scrollTop = 0;
        paintSheet();
        sheet.animate({ opacity: [0, 1] }, { duration: 250, easing: 'ease-out' });
    }

    function closeSheet() {
        sheetThemeId = null;
        sheet.classList.remove('on');
    }

    document.getElementById('sheet-close').addEventListener('click', closeSheet);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (stage.querySelector('.editing')) {
                clearEditing();
            } else {
                closeSheet();
            }
        }
    });
    // Toucher le fond sombre ferme la liste, comme sur un téléphone.
    sheet.addEventListener('click', function (event) {
        if (event.target === sheet) {
            closeSheet();
        }
    });

    zonesEl.addEventListener('click', function (event) {
        const name = event.target.closest('.zone-name');
        if (name) {
            openSheet(Number(name.closest('.zone').dataset.theme));
        }
    });

    /* ===================== DONNÉES ===================== */

    function render() {
        if (busy) {
            return;
        }
        paintBoard();
        if (sheetThemeId !== null) {
            paintSheet();
        }
    }

    function apply(data) {
        if (actions > 0) {
            return;
        }
        revision = data.revision;
        themes = data.themes;
        const status = document.getElementById('status');
        status.textContent = STATUS[data.status] || '';
        status.classList.toggle('on', !!STATUS[data.status]);
        if (busy) {
            pending = true;
            return;
        }
        render();
    }

    function poll() {
        clearTimeout(pollTimer);
        if (inflight) {
            again = true;
            return;
        }
        inflight = true;
        fetch(API + '?rev=' + encodeURIComponent(revision), { headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (response.status === 404) {
                    document.getElementById('gone').classList.add('on');
                    return null;
                }
                if (response.status === 204) {
                    return null;
                }
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                document.getElementById('offline').style.display = 'none';
                if (data) {
                    apply(data);
                }
            })
            .catch(function () { document.getElementById('offline').style.display = 'block'; })
            .finally(function () {
                inflight = false;
                if (again) {
                    again = false;
                    revision = '';
                    poll();
                } else {
                    pollTimer = setTimeout(poll, POLL_MS);
                }
            });
    }

    // Relecture immédiate après une action, sans attendre le prochain cycle.
    function refresh() {
        revision = '';
        poll();
    }

    function send(path, method, body) {
        actions += 1;
        return fetch(API + path, {
            method: method,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
        }).catch(function () {
            document.getElementById('offline').style.display = 'block';
        }).finally(function () {
            actions -= 1;
            refresh();
        });
    }

    function themeOf(questionId) {
        return themes.find(function (theme) {
            return theme.questions.some(function (question) { return question.id === questionId; });
        });
    }

    function arrange(theme, ids) {
        // Copie locale à jour : un rafraîchissement avant la réponse ne remet pas l'ancien ordre.
        const byId = {};
        theme.questions.forEach(function (question) { byId[question.id] = question; });
        theme.questions = ids.map(function (id) { return byId[id]; }).filter(Boolean);
        return send('/arrange', 'POST', { theme_id: theme.id, ids: ids });
    }

    function remove(element) {
        const id = Number(element.dataset.id);
        const theme = themeOf(id);
        if (theme) {
            theme.questions = theme.questions.filter(function (question) { return question.id !== id; });
            theme.total -= 1;
        }
        element.classList.remove('editing');
        element.animate({ opacity: [1, 0], transform: ['scale(1)', 'scale(.92)'] }, { duration: LEAVE_MS, easing: 'ease-in', fill: 'forwards' })
            .finished.then(function () { render(); });
        send('/questions/' + id, 'DELETE');
    }

    /* ===================== APPUI LONG ===================== */

    function clearEditing(except) {
        stage.querySelectorAll('.editing').forEach(function (el) {
            if (el !== except) {
                el.classList.remove('editing');
            }
        });
    }

    /**
     * Toucher et souris : appui long pour saisir, puis glisser. Un mouvement avant la fin de
     * l'appui est un défilement et annule la saisie.
     */
    function pressable(root, selector, handlers) {
        let press = null;
        let lastTouch = 0;

        function start(target, x, y) {
            if (target.closest('.remove') || target.closest('.zone-name')) {
                return;
            }
            const el = target.closest(selector);
            if (!el || !root.contains(el)) {
                return;
            }
            press = { el: el, x0: x, y0: y, active: false, moved: false };
            press.timer = setTimeout(function () {
                press.active = true;
                busy = true;
                if (navigator.vibrate) {
                    navigator.vibrate(15);
                }
                handlers.grab(press.el, { x: x, y: y });
            }, LONG_PRESS_MS);
        }

        function move(x, y, event) {
            if (!press) {
                return;
            }
            if (!press.active) {
                if (Math.hypot(x - press.x0, y - press.y0) > MOVE_TOLERANCE_PX) {
                    clearTimeout(press.timer);
                    press = null;
                }
                return;
            }
            if (event.cancelable) {
                event.preventDefault();
            }
            if (Math.hypot(x - press.x0, y - press.y0) > MOVE_TOLERANCE_PX) {
                press.moved = true;
            }
            handlers.drag(press.el, { x: x, y: y, dx: (x - press.x0) / scale, dy: (y - press.y0) / scale });
        }

        function end(point) {
            if (!press) {
                return;
            }
            clearTimeout(press.timer);
            const done = press;
            press = null;
            if (done.active) {
                handlers.drop(done.el, point, done.moved);
                busy = false;
                if (pending) {
                    pending = null;
                    render();
                }
            }
        }

        root.addEventListener('touchstart', function (event) {
            lastTouch = Date.now();
            if (event.touches.length === 1) {
                start(event.target, event.touches[0].clientX, event.touches[0].clientY);
            }
        }, { passive: true });
        document.addEventListener('touchmove', function (event) {
            move(event.touches[0].clientX, event.touches[0].clientY, event);
        }, { passive: false });
        document.addEventListener('touchend', function (event) {
            lastTouch = Date.now();
            const touch = event.changedTouches[0];
            end({ x: touch.clientX, y: touch.clientY });
        });
        document.addEventListener('touchcancel', function () { end(null); });

        root.addEventListener('mousedown', function (event) {
            if (event.button === 0 && Date.now() - lastTouch > GHOST_MOUSE_MS) {
                start(event.target, event.clientX, event.clientY);
            }
        });
        document.addEventListener('mousemove', function (event) { move(event.clientX, event.clientY, event); });
        document.addEventListener('mouseup', function (event) { end({ x: event.clientX, y: event.clientY }); });
    }

    /* --- Tableau : glisser une note sur une autre du même thème pour les échanger --- */

    function slotUnder(point, dragged) {
        if (!point) {
            return null;
        }
        const el = document.elementFromPoint(point.x, point.y);
        const slot = el ? el.closest('.slot') : null;
        if (!slot || slot === dragged || slot.parentElement !== dragged.parentElement) {
            return null;
        }
        return slot;
    }

    pressable(zonesEl, '.slot', {
        grab: function (slot) {
            clearEditing(slot);
            slot.classList.add('editing');
        },
        drag: function (slot, point) {
            slot.classList.add('dragging');
            slot.style.transform = 'translate(' + point.dx + 'px, ' + point.dy + 'px)';
            zonesEl.querySelectorAll('.slot.target').forEach(function (el) { el.classList.remove('target'); });
            const target = slotUnder(point, slot);
            if (target) {
                target.classList.add('target');
            }
        },
        drop: function (slot, point, moved) {
            const target = slotUnder(point, slot);
            zonesEl.querySelectorAll('.slot.target').forEach(function (el) { el.classList.remove('target'); });
            const rects = positions(zonesEl, '.slot');
            slot.classList.remove('dragging');
            slot.style.transform = '';

            if (!moved) {
                // Appui long sans glisser : la croix reste affichée.
                return;
            }
            slot.classList.remove('editing');
            if (!target) {
                glideFrom(rects);
                return;
            }

            const theme = themeOf(Number(slot.dataset.id));
            const ids = theme.questions.map(function (question) { return question.id; });
            const a = ids.indexOf(Number(slot.dataset.id));
            const b = ids.indexOf(Number(target.dataset.id));
            ids[a] = Number(target.dataset.id);
            ids[b] = Number(slot.dataset.id);

            // Échange immédiat à l'écran, sans attendre le serveur.
            const parent = slot.parentElement;
            const marker = document.createElement('div');
            parent.insertBefore(marker, slot);
            parent.insertBefore(slot, target);
            parent.insertBefore(target, marker);
            marker.remove();
            markStack(parent, theme, parent.children.length);
            glideFrom(rects);

            arrange(theme, ids);
        },
    });

    /* --- Liste : glisser pour insérer à une autre place --- */

    let autoScroll = null;
    let lastPoint = null;
    let orderBefore = '';

    function sheetIds() {
        return Array.from(sheetList.querySelectorAll('.item')).map(function (item) { return Number(item.dataset.id); });
    }

    function nextItem(item) {
        let next = item.nextElementSibling;
        while (next && !next.classList.contains('item')) {
            next = next.nextElementSibling;
        }
        return next;
    }

    // La carte saisie se place devant la première carte dont le milieu est sous le doigt.
    function placeAt(item, point) {
        const others = Array.from(sheetList.querySelectorAll('.item')).filter(function (other) { return other !== item; });
        const reference = others.find(function (other) {
            const rect = other.getBoundingClientRect();
            return point.y < rect.top + rect.height / 2;
        }) || null;
        if (reference === nextItem(item)) {
            return;
        }
        const rects = positions(sheetList, '.item');
        if (reference) {
            sheetList.insertBefore(item, reference);
        } else {
            others[others.length - 1].after(item);
        }
        renumber();
        rects.delete(item);
        glideFrom(rects);
    }

    function startAutoScroll() {
        stopAutoScroll();
        autoScroll = setInterval(function () {
            if (!lastPoint) {
                return;
            }
            const box = sheetList.getBoundingClientRect();
            const edge = SCROLL_EDGE_PX * scale;
            let step = 0;
            if (lastPoint.y < box.top + edge) {
                step = -SCROLL_STEP_PX;
            } else if (lastPoint.y > box.bottom - edge) {
                step = SCROLL_STEP_PX;
            }
            if (step) {
                sheetList.scrollTop += step;
                const item = sheetList.querySelector('.item.dragging');
                if (item) {
                    placeAt(item, lastPoint);
                }
            }
        }, 30);
    }

    function stopAutoScroll() {
        clearInterval(autoScroll);
        autoScroll = null;
        lastPoint = null;
    }

    pressable(sheetList, '.item', {
        grab: function (item) {
            clearEditing(item);
            item.classList.add('editing', 'dragging');
            orderBefore = sheetIds().join(',');
            startAutoScroll();
        },
        drag: function (item, point) {
            lastPoint = point;
            placeAt(item, point);
        },
        drop: function (item, point, moved) {
            stopAutoScroll();
            item.classList.remove('dragging');
            const ids = sheetIds();
            if (!moved || ids.join(',') === orderBefore) {
                return;
            }
            item.classList.remove('editing');
            const theme = sheetTheme();
            arrange(theme, ids);
        },
    });

    /* --- Croix et sortie du mode édition --- */

    stage.addEventListener('click', function (event) {
        const button = event.target.closest('.remove');
        if (button) {
            remove(button.closest('.slot, .item'));
            return;
        }
        if (!event.target.closest('.editing')) {
            clearEditing();
        }
    });

    // Le menu « Copier / Enregistrer l'image » d'Android et de la souris gênerait l'appui long.
    stage.addEventListener('contextmenu', function (event) { event.preventDefault(); });

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () {
            zonesEl.querySelectorAll('.note p').forEach(shrinkToFit);
        });
    }

    poll();
})();
</script>
</body>
</html>
