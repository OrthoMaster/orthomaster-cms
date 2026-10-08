{{--
    Page de démonstration : https://expert.orthomaster.fr/expert_demo
    Données fictives : App\Support\Brochures\DemoExpert
    Panneau « Personnaliser » : couleur et modèle 3D (aperçu uniquement, rien n'est enregistré).
--}}
@php
    $palettes = \App\Support\Brochures\BrochureDraft::PALETTES;
    $models = \App\Support\Brochures\BrochureDraft::MODELS;
@endphp
@extends('brochures.layout', [
    'title'       => $name.', partenaire OrthoMaster (démonstration)',
    'description' => 'Exemple de page partenaire OrthoMaster.',
    'palette'     => $demo['palette'],
    'model'       => $demo['model'],
])

@push('head')
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('brochures/css/brochures-editor.css') }}?v={{ filemtime(public_path('brochures/css/brochures-editor.css')) }}">
    <script type="module" src="{{ asset('brochures/js/demo-picker.js') }}?v={{ filemtime(public_path('brochures/js/demo-picker.js')) }}"></script>
@endpush

@section('content')
    {{-- ---------------- Panneau : couleur et modèle 3D ---------------- --}}
    <aside class="picker" id="picker" data-open="true" aria-label="Personnaliser la page">
        <button type="button" class="pk-toggle" id="pkToggle" aria-expanded="true" aria-controls="pkBody">
            Personnaliser
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>
        </button>
        <div class="pk-body" id="pkBody">
            <p class="pk-h" id="lblColor">Couleur principale</p>
            <div class="swatches" role="radiogroup" aria-labelledby="lblColor">
                @foreach ($palettes as $key => [$label, $hex])
                    <button type="button" class="sw" role="radio" data-palette="{{ $key }}" data-label="{{ $label }}"
                            aria-checked="{{ $demo['palette'] === $key ? 'true' : 'false' }}"
                            aria-label="{{ $label }}" title="{{ $label }}" style="background: {{ $hex }}"></button>
                @endforeach
            </div>
            <p class="pk-name" id="palName">{{ $palettes[$demo['palette']][0] }}</p>

            <label class="ed-field">
                <span class="pk-h">Modèle 3D</span>
                <select id="edModel" class="ed-select">
                    @foreach ($models as $key => $label)
                        <option value="{{ $key }}" @selected($demo['model'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </aside>

    @include('brochures.partials.hero', [
        'avatar'      => null,   // silhouette (aucun $expert sur cette page)
        'honorific'   => $demo['honorific'],
        'name'        => $name,
        'credentials' => $demo['credentials'],
        'expertise'   => $demo['expertise'],
        'lede'        => $demo['lede'],
        'places'      => $demo['places'],
        'doctolib'    => $demo['doctolib'],
        'linkedin'    => $demo['linkedin'],
        'facebook'    => $demo['facebook'],
    ])

    @foreach (\App\Support\Brochures\BrochureDraft::TIMELINES as $key => $label)
        @if (!empty($demo[$key]))
            @include('brochures.partials.timeline', [
                'id'    => $key,
                'title' => $demo['titles'][$key],
                'items' => $demo[$key],
            ])
        @endif
    @endforeach
@endsection