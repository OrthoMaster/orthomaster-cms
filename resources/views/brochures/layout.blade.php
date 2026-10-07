{{--
    Gabarit commun des pages partenaires OrthoMaster.

    Paramètres (via @extends('brochures.layout', [...]) dans la vue de l'expert) :
      title        Titre de l'onglet
      description  Meta description (facultatif)
      palette      turquoise | orange | ocean | rose | lavande   (défaut : turquoise)
      model        spine | knee | hand | foot | legs              (défaut : spine)

    Fournis par BrochureController : $showOrthoMaster, $videos, $registerUrl, $orthomasterUrl
--}}
@php
    $palette = $palette ?? 'turquoise';
    $model = $model ?? 'spine';
    $showOrthoMaster = $showOrthoMaster ?? false;
@endphp
<!doctype html>
<html lang="fr" data-palette="{{ $palette }}"
      style="--om-logo:url('{{ asset('brochures/img/orthomaster-logo.png') }}')">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Page partenaire OrthoMaster' }}</title>
    @if (!empty($description))
        <meta name="description" content="{{ $description }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,600;12..96,800&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
    @php($v = fn ($p) => asset($p).'?v='.filemtime(public_path($p)))
    <link rel="stylesheet" href="{{ $v('brochures/css/brochures.css') }}">
    <script type="module" src="{{ $v('brochures/js/app.js') }}"></script>
    @stack('head')
</head>
<body data-model="{{ $model }}" data-models-url="{{ asset('brochures/models') }}">
<canvas id="scene" aria-hidden="true"></canvas>

<main id="top">
    <div class="wrap">
        {{-- Contenu propre à chaque expert : hero, formation, parcours, sections supplémentaires… --}}
        @yield('content')

        {{-- Blocs OrthoMaster communs : uniquement avec ?inapp=WEB --}}
        @if ($showOrthoMaster)
            @include('brochures.partials.videos')
            @include('brochures.partials.join')
        @endif

        @include('brochures.partials.footer')
    </div>
</main>

@stack('scripts')
</body>
</html>