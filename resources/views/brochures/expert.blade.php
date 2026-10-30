{{--
    Page publique d'un expert : https://expert.orthomaster.fr/{slug}
    (aussi /experts/{slug} sur les autres domaines ; l'adresse canonique est celle du sous-domaine).

    $brochure : BrochureDraft::fromRecord($expert, 'brochure', $expert)
      - profil (titre, références, présentation, couleur) : colonnes de l'expert, comme dans l'application ;
      - page (modèle 3D, lieux, liens, formation, parcours) : version publiée.
    Domaines et photo : lus sur l'expert par le partial hero.
--}}
@php
    $fullName = trim(($brochure['honorific'] !== '' ? $brochure['honorific'].' ' : '').$expert->name);
    $description = $brochure['lede'] !== ''
        ? \Illuminate\Support\Str::limit($brochure['lede'], 160)
        : $fullName.', partenaire OrthoMaster.';
@endphp
@extends('brochures.layout', [
    'title'       => $fullName.', partenaire OrthoMaster',
    'description' => $description,
    'palette'     => $brochure['palette'],
    'model'       => $brochure['model'],
])

@push('head')
    @if (!empty($canonicalUrl))
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
    @endif
    <meta property="og:type" content="profile">
    <meta property="og:title" content="{{ $fullName }}, partenaire OrthoMaster">
    <meta property="og:description" content="{{ $description }}">
    @if ($expert->avatar_url)
        <meta property="og:image" content="{{ $expert->avatar_url }}">
    @endif
@endpush

@section('content')
    @include('brochures.partials.hero', [
        'honorific'   => $brochure['honorific'],
        'name'        => $expert->name,
        'credentials' => $brochure['credentials'],
        'lede'        => $brochure['lede'],
        'places'      => $brochure['places'],
        'doctolib'    => $brochure['doctolib'],
        'linkedin'    => $brochure['linkedin'],
        'facebook'    => $brochure['facebook'],
    ])

    @foreach (\App\Support\Brochures\BrochureDraft::TIMELINES as $key => $label)
        @if (!empty($brochure[$key]))
            @include('brochures.partials.timeline', [
                'id'    => $key,
                'title' => $brochure['titles'][$key],
                'items' => $brochure[$key],
            ])
        @endif
    @endforeach
@endsection
