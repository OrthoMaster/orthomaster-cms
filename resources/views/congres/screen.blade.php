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

        /* --- Éléments communs --- */
        .congress { color: #FFAF32; font-size: 22px; font-weight: 600; }
        .qr { display: block; background: #fff; border-radius: 10px; overflow: hidden; }
        .qr svg { display: block; width: 100%; height: 100%; }
        .host { color: #D5DDF0; font-size: 20px; }
        .code { font-size: 46px; line-height: 56px; font-weight: 700; letter-spacing: 4px; }
        .brand { display: flex; align-items: center; justify-content: center; gap: 10px; color: #EB7B0F; font-size: 26px; font-weight: 400; }
        .brand img { width: 40px; height: 40px; border-radius: 8px; object-fit: contain; }

        /* --- Attente --- */
        #waiting-grid { position: absolute; inset: 72px 96px; display: grid; grid-template-columns: minmax(0, 1fr) 640px; gap: 96px; align-items: center; }
        #view-waiting .brand { justify-content: flex-start; margin-bottom: 24px; }
        #view-waiting .congress { font-size: 34px; }
        #view-waiting h1 { margin: 18px 0 0; font-size: 84px; line-height: 98px; font-weight: 700; overflow-wrap: anywhere; }
        .invite { margin: 56px 0 0; font-size: 38px; line-height: 50px; font-weight: 500; }
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

    <div class="view" id="view-waiting">
        <div id="waiting-grid">
            <div>
                @include('congres._brand')
                @if ($board->congress_name)
                    <div class="congress">{{ $board->congress_name }}</div>
                @endif
                <h1>{{ $board->title }}</h1>
                <div class="invite">Posez vos questions aux intervenants depuis votre téléphone.</div>
            </div>
            <div class="qr-big">
                <div class="qr" data-qr style="width: 640px; height: 640px; border-radius: 14px"></div>
                <div class="host" data-host></div>
                <div class="code">{{ $board->code }}</div>
            </div>
        </div>
    </div>

    <div class="view" id="view-closed">
        <div class="message">
            @include('congres._brand')
            <div class="congress">{{ $board->title }}</div>
            <h1>Séance terminée</h1>
            <div class="sub">Merci de votre participation</div>
        </div>
    </div>

    <div class="view" id="view-disabled">
        <div class="message">
            @include('congres._brand')
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
    const FADE_MS = 700;

    const stage = document.getElementById('stage');
    const offlineEl = document.getElementById('offline');

    let revision = '';
    let currentView = '';

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
        if (name === currentView) {
            return;
        }
        document.querySelectorAll('.view').forEach(function (el) {
            el.classList.toggle('on', el.id === 'view-' + name);
        });
        if (currentView) {
            document.getElementById('view-' + name).animate({ opacity: [0, 1] }, { duration: FADE_MS, easing: 'ease-out' });
        }
        currentView = name;
    }

    // La salle ne voit que l'invitation à poser une question, puis la fin de séance :
    // les questions sont traitées par les intervenants sur leur propre écran.
    function paint(data) {
        show(data.status === 'closed' ? 'closed' : 'waiting');
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

    drawQr();
    poll();
})();
</script>
</body>
</html>
