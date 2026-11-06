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
        /* --- Base --- */
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #FFF3E0; color: #1B1F2A; font-family: Poppins, Arial, sans-serif; font-size: 16px; line-height: 24px; }
        main { max-width: 480px; margin: 0 auto; padding: 24px 16px 40px; }
        /* Place pour remonter le champ au-dessus du clavier, même quand la page est courte. */
        body.typing main { padding-bottom: 70vh; }
        [hidden] { display: none !important; }

        /* --- En-tête --- */
        .brand { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; color: #EB7B0F; font-size: 20px; font-weight: 400; }
        .brand img { width: 32px; height: 32px; border-radius: 7px; object-fit: contain; }
        .congress { color: #A85F00; font-size: 14px; font-weight: 600; }
        h1 { margin: 4px 0 20px; font-size: 24px; line-height: 32px; font-weight: 600; overflow-wrap: anywhere; }

        /* --- Carte --- */
        .card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 14px rgba(29,52,112,.08); }
        label, .legend { display: block; margin: 0 0 8px; font-weight: 600; }
        input[type=text], textarea {
            width: 100%; padding: 12px 14px; border: 1px solid #CFD4DE; border-radius: 10px;
            font: inherit; font-size: 16px; color: inherit; background: #fff;
        }
        input[type=text]:focus, textarea:focus { outline: 2px solid #FF9600; border-color: #FF9600; }
        textarea { min-height: 112px; resize: none; }
        .counter { margin-top: 4px; text-align: right; color: #5B6270; font-size: 13px; }
        fieldset { margin: 0 0 18px; padding: 0; border: 0; }

        /* --- Thèmes --- */
        .themes { display: flex; flex-wrap: wrap; gap: 8px; }
        label.theme { position: relative; display: block; margin: 0; font-weight: 500; cursor: pointer; }
        .theme input { position: absolute; opacity: 0; inset: 0; margin: 0; width: 100%; height: 100%; }
        .theme span { display: block; min-height: 44px; padding: 10px 16px; border-radius: 999px; border: 2px solid transparent; font-size: 15px; font-weight: 500; }
        .theme input:checked + span { border-color: #1B1F2A; font-weight: 600; }
        .themes.invalid .theme span { box-shadow: 0 0 0 2px #C62828; }
        .theme input:focus-visible + span { outline: 2px solid #1D3470; outline-offset: 2px; }

        /* --- Actions --- */
        .primary { width: 100%; min-height: 50px; margin-top: 18px; border: 0; border-radius: 12px; background: #FF9600; color: #1B1F2A; font: inherit; font-weight: 600; cursor: pointer; }
        .primary:disabled { opacity: .55; cursor: default; }
        .link { padding: 0; border: 0; background: none; color: #1D3470; font: inherit; font-size: 14px; text-decoration: underline; cursor: pointer; }
        .who { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; font-size: 14px; }
        .error { margin-top: 12px; color: #C62828; font-size: 14px; }
        .notice { margin: 14px 0 0; color: #5B6270; font-size: 13px; line-height: 19px; }

        /* --- Confirmation --- */
        dialog { width: calc(100% - 32px); max-width: 400px; padding: 28px 20px 20px; border: 0; border-radius: 20px; color: inherit; text-align: center; box-shadow: 0 12px 40px rgba(29,52,112,.25); }
        dialog::backdrop { background: rgba(20,34,74,.55); }
        .check { display: block; width: 56px; height: 56px; margin: 0 auto 12px; }
        dialog h2 { margin: 0; font-size: 20px; line-height: 28px; font-weight: 600; }
        dialog p { margin: 4px 0 18px; color: #5B6270; font-size: 14px; line-height: 20px; }
        .discover { display: flex; align-items: center; justify-content: center; min-height: 48px; padding: 8px 14px; border-radius: 12px; background: #FFF3E0; color: #1B1F2A; font-size: 15px; font-weight: 600; text-decoration: none; }
        dialog .primary { margin-top: 10px; }

        .state { text-align: center; }
        .state strong { display: block; font-size: 20px; line-height: 28px; font-weight: 600; }
    </style>
</head>
<body>
<main>
    @include('congres._brand')
    @if ($board->congress_name)
        <div class="congress">{{ $board->congress_name }}</div>
    @endif
    <h1>{{ $board->title }}</h1>

    <div class="card state" id="state" hidden>
        <strong id="state-title"></strong>
        <span id="state-text"></span>
    </div>

    <form class="card" id="join" hidden>
        <label for="name">Votre nom</label>
        <input type="text" id="name" maxlength="60" autocomplete="name" required>
        <p class="notice">Votre nom et vos questions s'affichent sur l'écran de la salle.</p>
        <button type="submit" class="primary">Rejoindre</button>
        <div class="error" id="join-error" role="alert" hidden></div>
    </form>

    <form class="card" id="ask" hidden>
        <div class="who">
            <span id="who"></span>
            <button type="button" class="link" id="rename">Changer de nom</button>
        </div>

        <fieldset>
            <legend class="legend">Thème</legend>
            <div class="themes">
                @foreach ($board->themes as $theme)
                    <label class="theme">
                        <input type="radio" name="theme" value="{{ $theme->id }}">
                        <span style="background: {{ $theme->color }}">{{ $theme->name }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <label for="text" id="text-label">Votre question</label>
        <textarea id="text" maxlength="{{ $maxLength }}" required></textarea>
        <div class="counter"><span id="count">0</span> / {{ $maxLength }}</div>

        <div class="error" id="ask-error" role="alert" hidden></div>
        <button type="submit" class="primary" id="send">Envoyer</button>
    </form>
</main>

<dialog id="sent" aria-labelledby="sent-title">
    <svg class="check" viewBox="0 0 56 56" aria-hidden="true">
        <circle cx="28" cy="28" r="28" fill="#D0E8D5"/>
        <path d="M17 29l8 8 15-16" fill="none" stroke="#2E7D32" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <h2 id="sent-title">Question envoyée</h2>
    <p>Merci, votre question a bien été transmise.</p>
    <a class="discover" href="https://app.orthomaster.fr" target="_blank" rel="noopener">Voir plus de contenus OrthoMaster</a>
    <button type="button" class="primary" id="sent-close" autofocus>Poser une autre question</button>
</dialog>

<script>
(function () {
    const CODE = @json($board->code);
    const API = '/api/congres/' + CODE;
    const STORAGE_KEY = 'congres:' + CODE;
    const STATUS_POLL_MS = 15000;

    const el = (id) => document.getElementById(id);
    let status = @json($board->status);
    let identity = load();
    let statusTimer = null;

    // Le stockage peut être bloqué (navigation privée) : l'identité reste alors en mémoire pour la page.
    function load() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY));
        } catch (e) {
            return null;
        }
    }

    function save(value) {
        identity = value;
        try {
            if (value) {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
            } else {
                localStorage.removeItem(STORAGE_KEY);
            }
        } catch (e) {
            // Rien à faire : voir load().
        }
    }

    function render() {
        const live = status === 'live';
        el('state').hidden = live;
        el('join').hidden = !live || !!identity;
        el('ask').hidden = !live || !identity;

        if (status === 'draft') {
            el('state-title').textContent = 'La séance n\'a pas encore commencé';
            el('state-text').textContent = 'Cette page s\'ouvrira automatiquement.';
        } else if (status === 'closed') {
            el('state-title').textContent = 'Séance terminée';
            el('state-text').textContent = 'Merci de votre participation.';
        }

        if (identity) {
            el('who').textContent = identity.name;
        }

        // Hors séance seulement : en séance, une salle entière n'interroge pas le serveur pour rien.
        clearTimeout(statusTimer);
        if (!live) {
            statusTimer = setTimeout(refreshStatus, STATUS_POLL_MS);
        }
    }

    function refreshStatus() {
        fetch(API + '/status', { headers: { Accept: 'application/json' } })
            .then((response) => response.ok ? response.json() : Promise.reject())
            .then((data) => { status = data.status; })
            .catch(() => {})
            .finally(render);
    }

    function show(id, message) {
        const target = el(id);
        target.textContent = message;
        target.hidden = false;
    }

    function post(path, body, headers) {
        return fetch(API + path, {
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json', Accept: 'application/json' }, headers),
            body: JSON.stringify(body),
        });
    }

    el('join').addEventListener('submit', function (event) {
        event.preventDefault();
        el('join-error').hidden = true;

        const name = el('name').value.trim();
        if (name.length < 2) {
            show('join-error', 'Veuillez indiquer votre nom.');
            return;
        }

        post('/join', { name })
            .then((response) => response.ok ? response.json() : Promise.reject())
            .then((data) => {
                save({ token: data.token, name: data.name });
                render();
            })
            .catch(() => show('join-error', 'La connexion n\'a pas pu être établie. Veuillez réessayer.'));
    });

    el('rename').addEventListener('click', function () {
        el('name').value = identity ? identity.name : '';
        save(null);
        render();
    });

    // Le clavier du téléphone couvre le bas de l'écran : on remonte la question et le bouton Envoyer
    // en haut de la partie visible, une fois le clavier ouvert.
    const KEYBOARD_DELAY_MS = 350;
    let typingTimer = null;

    function revealQuestion() {
        const top = el('text-label').getBoundingClientRect().top + window.scrollY - 12;
        window.scrollTo({ top: top, behavior: 'smooth' });
    }

    el('text').addEventListener('focus', function () {
        clearTimeout(typingTimer);
        document.body.classList.add('typing');
        setTimeout(revealQuestion, KEYBOARD_DELAY_MS);
    });

    // Retrait différé : sur iOS, le champ perd le focus avant que le tap sur Envoyer ne soit pris en compte.
    el('text').addEventListener('blur', function () {
        typingTimer = setTimeout(function () { document.body.classList.remove('typing'); }, 400);
    });

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', function () {
            if (document.activeElement === el('text')) {
                revealQuestion();
            }
        });
    }

    el('text').addEventListener('input', function () {
        el('count').textContent = this.value.length;
    });

    function clearThemeError() {
        el('ask-error').hidden = true;
        document.querySelector('.themes').classList.remove('invalid');
    }

    document.querySelectorAll('input[name=theme]').forEach(function (input) {
        input.addEventListener('change', clearThemeError);
    });

    // Le thème est choisi à chaque question : une seconde question porte rarement sur le même sujet.
    el('sent-close').addEventListener('click', function () {
        el('sent').close();
        document.querySelectorAll('input[name=theme]').forEach(function (input) { input.checked = false; });
        clearThemeError();
        document.querySelector('fieldset').scrollIntoView({ block: 'start', behavior: 'smooth' });
    });

    el('ask').addEventListener('submit', function (event) {
        event.preventDefault();
        el('ask-error').hidden = true;

        const theme = document.querySelector('input[name=theme]:checked');
        const text = el('text').value.trim();

        if (!theme) {
            show('ask-error', 'Veuillez sélectionner un thème avant d\'envoyer votre question.');
            document.querySelector('.themes').classList.add('invalid');
            return;
        }
        if (text.length < 3) {
            show('ask-error', 'Veuillez saisir votre question.');
            return;
        }

        el('send').disabled = true;

        post('/questions', { theme_id: Number(theme.value), text }, { 'X-Participant-Token': identity.token })
            .then((response) => {
                if (response.ok) {
                    el('text').value = '';
                    el('count').textContent = '0';
                    el('sent').showModal();
                } else if (response.status === 401) {
                    save(null);
                    render();
                } else if (response.status === 409) {
                    refreshStatus();
                } else if (response.status === 429) {
                    show('ask-error', 'Vous avez envoyé plusieurs questions en peu de temps. Merci de patienter une minute.');
                } else {
                    show('ask-error', 'Votre question n\'a pas pu être envoyée. Veuillez réessayer.');
                }
            })
            .catch(() => show('ask-error', 'La connexion au réseau semble interrompue. Votre question a été conservée, veuillez réessayer.'))
            .finally(() => { el('send').disabled = false; });
    });

    render();
})();
</script>
</body>
</html>
