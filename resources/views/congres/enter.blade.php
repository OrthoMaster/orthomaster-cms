<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Poser une question</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- Base --- */
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #FFF3E0; color: #1B1F2A; font-family: Poppins, Arial, sans-serif; font-size: 16px; line-height: 24px; }
        main { max-width: 480px; margin: 0 auto; padding: 24px 16px 40px; }

        /* --- En-tête --- */
        .brand { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; color: #EB7B0F; font-size: 20px; font-weight: 400; }
        .brand img { width: 32px; height: 32px; border-radius: 7px; object-fit: contain; }
        h1 { margin: 0 0 20px; font-size: 24px; line-height: 32px; font-weight: 600; }

        /* --- Formulaire --- */
        .card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 14px rgba(29,52,112,.08); }
        label { display: block; margin: 0 0 8px; font-weight: 600; }
        input {
            width: 100%; padding: 12px 14px; border: 1px solid #CFD4DE; border-radius: 10px;
            font: inherit; font-size: 24px; font-weight: 600; letter-spacing: 4px; text-align: center; text-transform: uppercase; color: inherit;
        }
        input:focus { outline: 2px solid #FF9600; border-color: #FF9600; }
        button { width: 100%; min-height: 50px; margin-top: 18px; border: 0; border-radius: 12px; background: #FF9600; color: #1B1F2A; font: inherit; font-weight: 600; cursor: pointer; }
        button:disabled { opacity: .55; cursor: default; }
        .error { margin-top: 12px; color: #C62828; font-size: 14px; }
    </style>
</head>
<body>
<main>
    @include('congres._brand')
    <h1>Poser une question</h1>

    <form class="card" id="enter">
        <label for="code">Code affiché à l'écran</label>
        <input type="text" id="code" maxlength="8" autocomplete="off" autocapitalize="characters" spellcheck="false" autofocus required>
        <button type="submit" id="go">Continuer</button>
        <div class="error" id="error" role="alert" hidden></div>
    </form>
</main>

<script>
(function () {
    const form = document.getElementById('enter');
    const input = document.getElementById('code');
    const button = document.getElementById('go');
    const error = document.getElementById('error');

    function fail(message) {
        error.textContent = message;
        error.hidden = false;
        button.disabled = false;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const code = input.value.trim().toUpperCase();

        error.hidden = true;
        button.disabled = true;

        fetch('/api/congres/' + encodeURIComponent(code) + '/status', { headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (response.ok) {
                    window.location.href = '/congres/' + encodeURIComponent(code);
                } else if (response.status === 404) {
                    fail('Ce code ne correspond à aucune séance. Veuillez vérifier le code affiché à l\'écran.');
                } else {
                    fail('La connexion n\'a pas pu être établie. Veuillez réessayer.');
                }
            })
            .catch(function () { fail('La connexion au réseau semble interrompue. Veuillez réessayer.'); });
    });
})();
</script>
</body>
</html>
