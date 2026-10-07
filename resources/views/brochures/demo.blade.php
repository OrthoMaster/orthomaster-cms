{{--
    Éditeur /demo/{slug}
    Nom et avatar : base de données (non modifiables ici).
    Titre (Docteur, Professeur…), couleur, modèle 3D, textes, domaines d'expertise (liste), lieux d'exercice, liens,
    titres et entrées de Formation / Parcours : modifiables, enregistrés automatiquement en XML.
    Avatar : upload direct (enregistré sur l'expert, l'ancien fichier est supprimé).
--}}
@extends('brochures.layout')

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('brochures/css/brochures-editor.css') }}?v={{ filemtime(public_path('brochures/css/brochures-editor.css')) }}">
    <script type="module" src="{{ asset('brochures/js/editor.js') }}?v={{ filemtime(public_path('brochures/js/editor.js')) }}"></script>
@endpush

@section('content')
    {{-- ---------------- Barre de mode : Édition / Aperçu + état de l'enregistrement ---------------- --}}
    <div class="ed-bar" id="edBar" data-save-url="{{ $saveUrl }}" data-avatar-url="{{ $avatarUrl }}" @if ($exportUrl) data-export-url="{{ $exportUrl }}" @endif>
        <div class="ed-seg" role="group" aria-label="Mode d’affichage">
            <button type="button" class="ed-seg-btn" data-mode="edit" aria-pressed="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/></svg>
                Mode édition
            </button>
            <button type="button" class="ed-seg-btn" data-mode="view" aria-pressed="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                Aperçu
            </button>
        </div>
        <p class="ed-save" id="edStatus" role="status" data-state="{{ $savedAt ? 'saved' : 'idle' }}">
            <span class="ed-dot" aria-hidden="true"></span>
            <span class="ed-save-txt">{{ $savedAt ? "Enregistré le {$savedAt}" : 'Enregistrement automatique' }}</span>
        </p>
    </div>

    {{-- ---------------- Panneau : couleur, modèle, liens, administration ---------------- --}}
    <aside class="picker ed-panel" id="picker" data-open="true" aria-label="Personnaliser la page">
        <button type="button" class="pk-toggle" id="pkToggle" aria-expanded="true" aria-controls="pkBody">
            Personnaliser
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>
        </button>
        <div class="pk-body" id="pkBody">
            <p class="pk-hint">Cliquez sur un texte de la page pour le modifier. Les modifications sont enregistrées automatiquement.</p>

            <p class="pk-h" id="lblColor">Couleur principale</p>
            <div class="swatches" role="radiogroup" aria-labelledby="lblColor">
                @foreach ($palettes as $key => [$label, $hex])
                    <button type="button" class="sw" role="radio" data-palette="{{ $key }}" data-label="{{ $label }}"
                            aria-checked="{{ $draft['palette'] === $key ? 'true' : 'false' }}"
                            aria-label="{{ $label }}" title="{{ $label }}" style="background: {{ $hex }}"></button>
                @endforeach
            </div>
            <p class="pk-name" id="palName">{{ $palettes[$draft['palette']][0] }}</p>

            <label class="ed-field">
                <span class="pk-h">Modèle 3D</span>
                <select id="edModel" class="ed-select">
                    @foreach ($models as $key => $label)
                        <option value="{{ $key }}" @selected($draft['model'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <p class="pk-h ed-gap">Liens</p>
            <label class="ed-field"><span>Doctolib</span>
                <input type="url" data-link="doctolib" value="{{ $draft['doctolib'] }}" placeholder="https://www.doctolib.fr/…" inputmode="url" autocomplete="off">
            </label>
            <label class="ed-field"><span>LinkedIn</span>
                <input type="url" data-link="linkedin" value="{{ $draft['linkedin'] }}" placeholder="https://www.linkedin.com/in/…" inputmode="url" autocomplete="off">
            </label>
            <label class="ed-field"><span>Facebook</span>
                <input type="url" data-link="facebook" value="{{ $draft['facebook'] }}" placeholder="https://www.facebook.com/…" inputmode="url" autocomplete="off">
            </label>

            @if ($isAdmin)
                <div class="ed-admin">
                    <p class="pk-h">Administration</p>
                    <button type="button" class="ed-btn" id="edShare" data-url="{{ $shareUrl }}">Copier le lien pour le médecin</button>
                    <a class="ed-btn" id="edExport" href="{{ $exportUrl }}">Télécharger index.blade.php</a>
                </div>
            @endif
        </div>
    </aside>

    {{-- ---------------- Hero ---------------- --}}
    <section class="hero">
        <div class="col">
            {{-- Avatar : cliquer pour changer la photo (mode édition) --}}
            <div class="avatar ed-avatar fade" id="edAvatar">
                <img src="{{ $expert->avatar_url }}" alt="{{ $identity['name'] }}" @if (empty($expert->avatar_url)) hidden @endif>
                <svg class="silhouette" viewBox="0 0 100 100" fill="currentColor" aria-hidden="true" @if (!empty($expert->avatar_url)) hidden @endif><circle cx="50" cy="38" r="19"/><path d="M12 100c0-22 17-37 38-37s38 15 38 37z"/></svg>
                <label class="ed-avatar-btn" title="Changer la photo">
                    <input type="file" id="edAvatarInput" accept="image/jpeg,image/png,image/webp">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
                    <span>Changer la photo</span>
                </label>
            </div>

            <p class="partner fade">Partenaire <b>OrthoMaster</b></p>

            <h1 class="fade d2">
                <span class="dr" contenteditable="plaintext-only" data-field="honorific" data-placeholder="Titre (Docteur, Professeur…)">{{ $draft['honorific'] }}</span>
                <span class="ln">{{ $identity['name'] }}</span>
            </h1>

            <p class="role creds fade d3" contenteditable="plaintext-only" data-field="credentials" data-placeholder="Titres et diplômes (ex. MB BS, PhD)">{{ $draft['credentials'] }}</p>

            {{-- Domaines d'expertise : choisis dans la liste ExpertiseCatalog --}}
            <div class="ed-expertise">
                <ul class="expertise fade d3" data-list="expertise" aria-label="Domaines d’expertise">@foreach ($draft['expertise'] as $id)@if (isset($expertiseCatalog[$id]))@include('brochures.partials.editor.pill', ['id' => $id, 'label' => $expertiseCatalog[$id]])@endif @endforeach<li class="ed-add-li"><button type="button" class="ed-add small" id="edExpertiseBtn" aria-expanded="false" aria-controls="edExpertise">+ Domaine d’expertise</button></li></ul>
                <div class="ed-pop" id="edExpertise" role="dialog" aria-label="Choisir les domaines d’expertise" hidden>
                    <input type="search" class="ed-pop-search" id="edExpertiseSearch" placeholder="Rechercher un domaine…" autocomplete="off">
                    <div class="ed-pop-list">
                        @foreach ($expertiseCatalog as $id => $label)
                            <label class="ed-opt"><input type="checkbox" value="{{ $id }}" data-label="{{ $label }}" @checked(in_array($id, $draft['expertise'], true))><span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                    <div class="ed-pop-foot"><span id="edExpertiseCount"></span><button type="button" class="ed-btn" id="edExpertiseClose">Fermer</button></div>
                </div>
            </div>

            <p class="lede fade d3" contenteditable="plaintext-only" data-field="lede" data-placeholder="Quelques mots sur votre pratique et votre approche du soin">{{ $draft['lede'] }}</p>

            {{-- Lieux d'exercice : chaque ligne est facultative (une ville seule suffit) --}}
            <ul class="workplaces fade d3" data-list="places" aria-label="Lieux d’exercice">@foreach ($draft['places'] as $place)@include('brochures.partials.editor.place', ['place' => $place])@endforeach</ul>
            <button type="button" class="ed-add small ed-add-place" data-add-place>+ Lieu d’exercice</button>

            {{-- Chaque bouton n'apparaît que lorsque son lien est saisi. Le bouton OrthoMaster n'existe que sur la page officielle. --}}
            <div class="actions fade d4">
                <a class="btn" data-link-btn="doctolib" href="{{ $draft['doctolib'] ?: '#' }}" target="_blank" rel="noopener" @if (!$draft['doctolib']) hidden @endif>
                    <img class="ico" src="{{ asset('brochures/img/doctolib.png') }}" alt="" width="18" height="18">Doctolib
                </a>
                <a class="btn" data-link-btn="linkedin" href="{{ $draft['linkedin'] ?: '#' }}" target="_blank" rel="noopener" @if (!$draft['linkedin']) hidden @endif>
                    @include('brochures.partials.icons.linkedin')LinkedIn
                </a>
                <a class="btn" data-link-btn="facebook" href="{{ $draft['facebook'] ?: '#' }}" target="_blank" rel="noopener" @if (!$draft['facebook']) hidden @endif>
                    @include('brochures.partials.icons.facebook')Facebook
                </a>
            </div>

            <p class="hint fade d4">Faites défiler pour découvrir le parcours</p>
        </div>
    </section>

    {{-- ---------------- Formation / Parcours (titres modifiables) ---------------- --}}
    @foreach ($timelines as $key => $label)
        <section class="block" id="{{ $key }}">
            <div class="col panel">
                <h2 contenteditable="plaintext-only" data-title="{{ $key }}" data-placeholder="{{ $label }}">{{ $draft['titles'][$key] }}</h2>
                <button type="button" class="ed-add" data-add="{{ $key }}">+ Ajouter une entrée</button>
                <ol class="tl" data-list="{{ $key }}">@foreach ($draft[$key] as $entry)@include('brochures.partials.editor.entry', ['entry' => $entry])@endforeach</ol>
            </div>
        </section>
    @endforeach

    <template id="tplEntry">@include('brochures.partials.editor.entry', ['entry' => []])</template>
    <template id="tplPill">@include('brochures.partials.editor.pill', ['id' => '', 'label' => ''])</template>
    <template id="tplPlace">@include('brochures.partials.editor.place', ['place' => []])</template>
@endsection