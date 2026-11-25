<!doctype html>
<html lang="fr">
<head>
    @include('satisfaction._head')
    <title>Votre avis sur votre formation</title>
    <style>
        /* --- Informations pré-remplies --- */
        .info dt { color: #8A909C; font-size: 13px; font-weight: 500; }
        .info dd { margin: 0 0 12px; font-weight: 600; }
        .info dd:last-child { margin-bottom: 0; }
        .info ul { margin: 0; padding-left: 18px; }

        /* --- Questions --- */
        .q { margin-bottom: 24px; }
        .q:last-child { margin-bottom: 0; }
        .q-title { display: block; margin: 0 0 10px; font-weight: 600; }
        .q-num { color: #EB7B0F; }
        .q.invalid .q-title { color: #C62828; }
        .err { margin-top: 6px; color: #C62828; font-size: 14px; }

        /* --- Échelles : boutons radio en pastilles --- */
        .scale { display: flex; gap: 8px; }
        .scale label { flex: 1 1 0; position: relative; }
        .scale input { position: absolute; opacity: 0; pointer-events: none; }
        .scale span {
            display: block; padding: 10px 0; border: 1px solid #CFD4DE; border-radius: 10px;
            text-align: center; font-weight: 600; cursor: pointer; user-select: none;
        }
        .scale input:checked + span { background: #FF9600; border-color: #FF9600; color: #1B1F2A; }
        .scale input:focus-visible + span { outline: 2px solid #2D55AF; }
        .scale-legend { display: flex; justify-content: space-between; margin-top: 4px; color: #8A909C; font-size: 12px; }
        .scale.nps { gap: 4px; }
        .scale.nps span { padding: 8px 0; font-size: 14px; }

        /* --- Choix et champs libres --- */
        .choices label { display: flex; align-items: center; gap: 10px; padding: 8px 0; cursor: pointer; }
        .choices input { width: 18px; height: 18px; accent-color: #FF9600; }
        textarea {
            width: 100%; min-height: 90px; padding: 10px 12px; border: 1px solid #CFD4DE; border-radius: 10px;
            font: inherit; font-size: 15px; color: inherit; resize: vertical;
        }
        textarea:focus { outline: 2px solid #FF9600; border-color: #FF9600; }
        .check { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; }
        .check input { width: 18px; height: 18px; margin-top: 3px; accent-color: #FF9600; }
        .hint { margin-top: 10px; color: #5A6170; font-size: 14px; }
        .hint a { color: #EB7B0F; }

        button { width: 100%; min-height: 52px; border: 0; border-radius: 12px; background: #FF9600; color: #1B1F2A; font: inherit; font-weight: 600; cursor: pointer; }
        button:disabled { opacity: .55; cursor: default; }

        @media (max-width: 420px) {
            .scale.nps { flex-wrap: wrap; }
            .scale.nps label { flex: 0 0 calc((100% - 20px) / 6); }
        }
    </style>
</head>
<body>
<main>
    @include('congres._brand')
    <h1>Votre avis sur votre formation</h1>
    <p class="lead">Quelques mois après votre formation, dites-nous ce que vous en pensez. Environ 2 minutes.</p>

    <div class="card">
        <dl class="info">
            <dt>Apprenant</dt>
            <dd>{{ $name }}</dd>
            <dt>{{ count($packages) > 1 ? 'Formations' : 'Formation' }}</dt>
            <dd>
                @if (count($packages) > 1)
                    <ul>@foreach ($packages as $package)<li>{{ $package }}</li>@endforeach</ul>
                @else
                    {{ $packages[0] }}
                @endif
            </dd>
        </dl>
    </div>

    <form method="POST" action="{{ route('satisfaction.submit', $survey->token) }}" id="survey" novalidate>
        @csrf
        @php $n = 0; @endphp

        <div class="card">
            @foreach (\App\Models\SatisfactionSurvey::RATINGS as $field => $label)
                <div class="q @error($field) invalid @enderror">
                    <span class="q-title"><span class="q-num">{{ ++$n }}.</span> {{ $label }}</span>
                    <div class="scale" role="radiogroup">
                        @for ($i = 1; $i <= 5; $i++)
                            <label>
                                <input type="radio" name="{{ $field }}" value="{{ $i }}" required @checked(old($field) == $i)>
                                <span>{{ $i }}</span>
                            </label>
                        @endfor
                    </div>
                    <div class="scale-legend"><span>Pas du tout satisfait</span><span>Très satisfait</span></div>
                    @error($field)<div class="err">{{ $message }}</div>@enderror
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="q @error('practice') invalid @enderror">
                <span class="q-title"><span class="q-num">{{ ++$n }}.</span> Depuis la formation, avez-vous mis en pratique ce que vous avez appris ?</span>
                <div class="choices">
                    @foreach (\App\Models\SatisfactionSurvey::PRACTICE as $value => $label)
                        <label><input type="radio" name="practice" value="{{ $value }}" required @checked(old('practice') === $value)> {{ $label }}</label>
                    @endforeach
                </div>
                @error('practice')<div class="err">{{ $message }}</div>@enderror
            </div>

            <div class="q @error('recommend') invalid @enderror">
                <span class="q-title"><span class="q-num">{{ ++$n }}.</span> Recommanderiez-vous cette formation à un confrère ?</span>
                <div class="scale nps" role="radiogroup">
                    @for ($i = 0; $i <= 10; $i++)
                        <label>
                            <input type="radio" name="recommend" value="{{ $i }}" required @checked(old('recommend') !== null && old('recommend') == $i)>
                            <span>{{ $i }}</span>
                        </label>
                    @endfor
                </div>
                <div class="scale-legend"><span>Pas du tout probable</span><span>Très probable</span></div>
                @error('recommend')<div class="err">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="card">
            <div class="q @error('difficulties_flg') invalid @enderror">
                <span class="q-title"><span class="q-num">{{ ++$n }}.</span> Avez-vous rencontré des difficultés (techniques, accès, contenu) ?</span>
                <div class="choices">
                    <label><input type="radio" name="difficulties_flg" value="1" required @checked(old('difficulties_flg') === '1')> Oui</label>
                    <label><input type="radio" name="difficulties_flg" value="0" required @checked(old('difficulties_flg') === '0')> Non</label>
                </div>
                <div id="difficulties-detail" @if (old('difficulties_flg') !== '1') hidden @endif>
                    <textarea name="difficulties_text" maxlength="2000" placeholder="Précisez…">{{ old('difficulties_text') }}</textarea>
                </div>
                @error('difficulties_flg')<div class="err">{{ $message }}</div>@enderror
            </div>

            <div class="q">
                <label class="q-title" for="liked_text"><span class="q-num">{{ ++$n }}.</span> Qu'avez-vous le plus apprécié ?</label>
                <textarea id="liked_text" name="liked_text" maxlength="2000">{{ old('liked_text') }}</textarea>
            </div>

            <div class="q">
                <label class="q-title" for="suggestions_text"><span class="q-num">{{ ++$n }}.</span> Quelles améliorations nous suggéreriez-vous ?</label>
                <textarea id="suggestions_text" name="suggestions_text" maxlength="2000">{{ old('suggestions_text') }}</textarea>
            </div>
        </div>

        <div class="card">
            <label class="check">
                <input type="checkbox" name="recontact_flg" value="1" @checked(old('recontact_flg'))>
                <span>Je souhaite être recontacté(e)</span>
            </label>
            <p class="hint">Une réclamation ? <a href="https://www.orthomaster.fr/contact" target="_blank" rel="noopener">Contactez-nous ici</a>.</p>
        </div>

        <button type="submit" id="send">Envoyer mes réponses</button>
    </form>
</main>

<script>
(function () {
    const form = document.getElementById('survey');
    const detail = document.getElementById('difficulties-detail');

    form.querySelectorAll('input[name="difficulties_flg"]').forEach(function (input) {
        input.addEventListener('change', function () { detail.hidden = input.value !== '1'; });
    });

    // Contrôle côté navigateur pour signaler toutes les questions oubliées d'un coup ; le serveur revalide.
    form.addEventListener('submit', function (event) {
        let first = null;
        form.querySelectorAll('.q').forEach(function (q) {
            const radio = q.querySelector('input[type="radio"][required]');
            const missing = radio && !q.querySelector('input[name="' + radio.name + '"]:checked');
            q.classList.toggle('invalid', !!missing);
            if (missing && !first) first = q;
        });

        if (first) {
            event.preventDefault();
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        document.getElementById('send').disabled = true;
    });
})();
</script>
</body>
</html>
