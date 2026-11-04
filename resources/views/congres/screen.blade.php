<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $board->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- Scène : 1920×1080 mise à l'échelle, quel que soit le vidéoprojecteur --- */
        html, body { margin: 0; height: 100%; overflow: hidden; background: #14224A; }
        #stage {
            position: absolute; left: 50%; top: 50%; width: 1920px; height: 1080px;
            margin: -540px 0 0 -960px; overflow: hidden;
            color: #fff; background: #14224A; font-family: Poppins, Arial, sans-serif;
        }
        .view { position: absolute; inset: 0; display: none; }
        .view.on { display: block; }

        /* --- Tableau --- */
        #board-grid { position: absolute; inset: 36px; display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 24px; }
        #zones { min-width: 0; min-height: 0; display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 24px; }
        .zone { min-width: 0; min-height: 0; display: flex; flex-direction: column; gap: 12px; }
        .zone-head { flex: none; height: 52px; display: flex; align-items: center; justify-content: space-between; gap: 14px; }
        .zone-name { color: #1B1F2A; font-size: 26px; font-weight: 600; line-height: 36px; padding: 6px 20px; border-radius: 999px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .zone-count { font-size: 26px; font-weight: 600; white-space: nowrap; }
        .zone-notes { flex: 1; min-height: 0; display: grid; gap: 14px; }

        /* --- Note --- */
        .slot { position: relative; min-width: 0; min-height: 0; }
        .note {
            position: absolute; inset: 0; box-sizing: border-box; padding: 16px 18px 10px;
            color: #1B1F2A; border-radius: 6px; box-shadow: 0 8px 18px rgba(0,0,0,.35);
            display: flex; flex-direction: column; justify-content: space-between; gap: 4px;
            transform: rotate(var(--tilt));
        }
        .note.enter { animation: drop 420ms cubic-bezier(.2,1.3,.4,1); }
        @keyframes drop {
            from { opacity: 0; transform: scale(1.28) rotate(calc(var(--tilt) * 4)); }
            to { opacity: 1; transform: rotate(var(--tilt)); }
        }
        .note p { flex: 1; min-height: 0; margin: 0; font-weight: 500; overflow: hidden; overflow-wrap: anywhere; }
        .note span { flex: none; align-self: flex-end; max-width: 100%; color: #3B4150; font-size: 17px; font-weight: 500; line-height: 22px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* --- Colonne QR --- */
        #side { min-width: 0; box-sizing: border-box; padding: 28px; border-radius: 24px; background: #1D3470; display: flex; flex-direction: column; align-items: center; justify-content: space-between; text-align: center; }
        .congress { color: #FFAF32; font-size: 22px; font-weight: 600; }
        #side h1 { margin: 12px 0 0; font-size: 38px; line-height: 46px; font-weight: 600; overflow-wrap: anywhere; }
        .qr { display: block; background: #fff; border-radius: 10px; overflow: hidden; }
        .qr svg { display: block; width: 100%; height: 100%; }
        .scan { margin-top: 14px; font-size: 24px; line-height: 32px; font-weight: 500; }
        .host { color: #D5DDF0; font-size: 20px; }
        .code { font-size: 46px; line-height: 56px; font-weight: 700; letter-spacing: 4px; }
        .brand { color: #FFAF32; font-size: 22px; font-weight: 700; }

        /* --- Attente --- */
        #waiting-grid { position: absolute; inset: 72px 96px; display: grid; grid-template-columns: minmax(0, 1fr) 640px; gap: 96px; align-items: center; }
        #view-waiting .congress { font-size: 34px; }
        #view-waiting h1 { margin: 18px 0 0; font-size: 84px; line-height: 98px; font-weight: 700; overflow-wrap: anywhere; }
        .invite { margin: 56px 0; font-size: 38px; line-height: 50px; font-weight: 500; }
        #chips { display: flex; flex-wrap: wrap; gap: 16px; }
        #chips span { color: #1B1F2A; font-size: 30px; font-weight: 600; line-height: 40px; padding: 8px 26px; border-radius: 999px; }
        .qr-big { display: flex; flex-direction: column; align-items: center; gap: 24px; }
        .qr-big .host { font-size: 30px; }
        .qr-big .code { font-size: 72px; line-height: 80px; letter-spacing: 6px; }

        /* --- Messages --- */
        .message { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 28px; text-align: center; padding: 0 120px; }
        .message .congress { font-size: 34px; }
        .message h1 { margin: 0; font-size: 104px; line-height: 120px; font-weight: 700; }
        .message div.sub { font-size: 40px; font-weight: 500; }

        #offline { position: absolute; right: 16px; bottom: 12px; width: 14px; height: 14px; border-radius: 50%; background: #FF4B4B; display: none; }
    </style>
</head>
<body>
<div id="stage">

    <div class="view" id="view-board">
        <div id="board-grid">
            <div id="zones"></div>
            <aside id="side">
                <div>
                    @if ($board->congress_name)
                        <div class="congress">{{ $board->congress_name }}</div>
                    @endif
                    <h1>{{ $board->title }}</h1>
                </div>
                <div>
                    <div class="qr" data-qr style="width: 324px; height: 324px"></div>
                    <div class="scan">Scannez pour poser votre question</div>
                </div>
                <div>
                    <div class="host" data-host></div>
                    <div class="code">{{ $board->code }}</div>
                </div>
                <div class="brand">OrthoMaster</div>
            </aside>
        </div>
    </div>

    <div class="view" id="view-waiting">
        <div id="waiting-grid">
            <div>
                @if ($board->congress_name)
                    <div class="congress">{{ $board->congress_name }}</div>
                @endif
                <h1>{{ $board->title }}</h1>
                <div class="invite">Posez vos questions aux intervenants depuis votre téléphone.</div>
                <div id="chips"></div>
            </div>
            <div class="qr-big">
                <div class="qr" data-qr style="width: 640px; height: 640px; border-radius: 14px"></div>
                <div class="host" data-host></div>
                <div class="code">{{ $board->code }}</div>
            </div>
        </div>
    </div>

    <div class="view" id="view-draft">
        <div class="message">
            @if ($board->congress_name)
                <div class="congress">{{ $board->congress_name }}</div>
            @endif
            <h1>{{ $board->title }}</h1>
            <div class="sub">La séance va bientôt commencer</div>
        </div>
    </div>

    <div class="view" id="view-closed">
        <div class="message">
            <div class="congress">{{ $board->title }}</div>
            <h1>Séance terminée</h1>
            <div class="sub">Merci de votre participation</div>
        </div>
    </div>

    <div class="view" id="view-disabled">
        <div class="message">
            <h1>Lien désactivé</h1>
        </div>
    </div>

    <div id="offline"></div>
</div>

<script src="{{ asset('js/congres/qrcode.js') }}"></script>
<script>
(function () {
    const READER_URL = @json($board->readerUrl());
    const DATA_URL = @json('/api/congres/ecran/' . $board->screen_token);
    const POLL_MS = 2000;
    const MIN_FONT_PX = 18;

    const stage = document.getElementById('stage');
    const zonesEl = document.getElementById('zones');
    const chipsEl = document.getElementById('chips');
    const offlineEl = document.getElementById('offline');

    let revision = '';
    let firstPaint = true;

    function fit() {
        const scale = Math.min(window.innerWidth / 1920, window.innerHeight / 1080);
        stage.style.transform = 'scale(' + scale + ')';
    }
    window.addEventListener('resize', fit);
    fit();

    // Double-clic : plein écran, sans bouton visible sur la projection.
    document.addEventListener('dblclick', function () {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen();
        }
    });

    function drawQr() {
        const qr = qrcode(0, 'M');
        qr.addData(READER_URL);
        qr.make();
        const size = qr.getModuleCount();
        const quiet = 4;
        const full = size + quiet * 2;
        let path = '';
        for (let r = 0; r < size; r++) {
            for (let c = 0; c < size; c++) {
                if (qr.isDark(r, c)) {
                    path += 'M' + (c + quiet) + ' ' + (r + quiet) + 'h1v1h-1z';
                }
            }
        }
        const svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + full + ' ' + full + '" shape-rendering="crispEdges" role="img" aria-label="QR code"><path d="' + path + '"/></svg>';
        document.querySelectorAll('[data-qr]').forEach(function (el) { el.innerHTML = svg; });

        const host = READER_URL.replace(/^https?:\/\//, '').replace(/\/[^/]+$/, '');
        document.querySelectorAll('[data-host]').forEach(function (el) { el.textContent = host; });
    }

    function show(name) {
        document.querySelectorAll('.view').forEach(function (el) {
            el.classList.toggle('on', el.id === 'view-' + name);
        });
    }

    // La taille suit la longueur : une question courte reste lisible du fond de la salle.
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

    // Filet de sécurité : une question longue aux mots larges ne doit jamais être coupée.
    function shrinkToFit(text) {
        let size = parseFloat(text.style.fontSize);
        while (text.scrollHeight > text.clientHeight && size > MIN_FONT_PX) {
            size -= 1;
            text.style.fontSize = size + 'px';
            text.style.lineHeight = Math.round(size * 1.27) + 'px';
        }
    }

    function buildSlot(question, theme, animate) {
        const slot = document.createElement('div');
        slot.className = 'slot';
        slot.dataset.id = question.id;

        const note = document.createElement('article');
        note.className = 'note' + (animate ? ' enter' : '');
        note.style.background = theme.color;
        // Inclinaison dérivée de l'id : un rechargement ne déplace pas les notes.
        note.style.setProperty('--tilt', (((question.id * 37) % 5) - 2) * 0.6 + 'deg');

        const text = document.createElement('p');
        const type = typeFor(question.text.length, theme.layout.rows === 3);
        text.style.fontSize = type[0];
        text.style.lineHeight = type[1];
        text.textContent = question.text;

        const author = document.createElement('span');
        author.textContent = question.author;

        note.append(text, author);
        slot.append(note);
        return slot;
    }

    function zoneFor(theme) {
        let zone = zonesEl.querySelector('[data-theme="' + theme.id + '"]');
        if (!zone) {
            zone = document.createElement('section');
            zone.className = 'zone';
            zone.dataset.theme = theme.id;
            zone.innerHTML = '<div class="zone-head"><span class="zone-name"></span><span class="zone-count"></span></div><div class="zone-notes"></div>';
        }
        return zone;
    }

    function paintBoard(themes) {
        zonesEl.style.gridTemplateRows = themes.length >= 4 ? 'repeat(2, minmax(0, 1fr))' : 'minmax(0, 1fr)';

        const keep = [];
        themes.forEach(function (theme) {
            const zone = zoneFor(theme);
            keep.push(zone);
            zone.style.gridColumn = 'span ' + theme.layout.span;

            const name = zone.querySelector('.zone-name');
            name.textContent = theme.name;
            name.style.background = theme.color;
            zone.querySelector('.zone-count').textContent = theme.total + (theme.total > 1 ? ' questions' : ' question');

            const notes = zone.querySelector('.zone-notes');
            notes.style.gridTemplateColumns = 'repeat(' + theme.layout.cols + ', minmax(0, 1fr))';
            notes.style.gridTemplateRows = 'repeat(' + theme.layout.rows + ', minmax(0, 1fr))';

            // Les notes déjà affichées sont réutilisées : seule celle qui arrive s'anime.
            const current = {};
            notes.querySelectorAll('.slot').forEach(function (slot) { current[slot.dataset.id] = slot; });
            const wanted = theme.questions.map(function (question) {
                return current[question.id] || buildSlot(question, theme, !firstPaint);
            });
            notes.replaceChildren.apply(notes, wanted);
        });
        zonesEl.replaceChildren.apply(zonesEl, keep);
        zonesEl.querySelectorAll('.note p').forEach(shrinkToFit);
    }

    function paintChips(themes) {
        chipsEl.replaceChildren.apply(chipsEl, themes.map(function (theme) {
            const chip = document.createElement('span');
            chip.textContent = theme.name;
            chip.style.background = theme.color;
            return chip;
        }));
    }

    function paint(data) {
        if (data.status === 'draft') {
            show('draft');
        } else if (data.status === 'closed') {
            show('closed');
        } else if (data.themes.every(function (theme) { return theme.total === 0; })) {
            paintChips(data.themes);
            show('waiting');
        } else {
            // Visible d'abord : un élément masqué n'a pas de hauteur à mesurer.
            show('board');
            paintBoard(data.themes);
            firstPaint = false;
        }
    }

    function poll() {
        fetch(DATA_URL + '?rev=' + encodeURIComponent(revision), { headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (response.status === 404) {
                    show('disabled');
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
                offlineEl.style.display = 'none';
                if (data) {
                    revision = data.revision;
                    paint(data);
                }
            })
            // Réseau coupé : on garde ce qui est affiché et on réessaie.
            .catch(function () { offlineEl.style.display = 'block'; })
            .finally(function () { setTimeout(poll, POLL_MS); });
    }

    // Mesurées avant le chargement de Poppins, les notes seraient calculées sur la police de repli.
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () {
            document.querySelectorAll('.note p').forEach(shrinkToFit);
        });
    }

    drawQr();
    poll();
})();
</script>
</body>
</html>
