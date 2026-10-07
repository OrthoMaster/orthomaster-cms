{{--
    Guillaume Lê
    Généré le 06/10/2026 15:12 depuis /demo/guillaume-le
    À placer dans : resources/views/brochures/experts/41/index.blade.php
--}}
@php
    $brochure = [
        "layout" => [
            "title" => "Guillaume Lê, partenaire OrthoMaster",
            "palette" => "ocean",
            "model" => "spine",
        ],
        "hero" => [
            "honorific" => "Docteur",
            "name" => "Guillaume Lê",
            "credentials" => "",
            "expertise" => [],
            "lede" => "",
            "doctolib" => "",
            "linkedin" => "",
            "facebook" => "",
        ],
        "formation" => [
            "id" => "formation",
            "title" => "Formations",
            "items" => [
                [
                    "year" => "2017 - 2018",
                    "title" => "Business Administration and Management, General",
                    "place" => "EM Strasbourg Business School MBA",
                ],
                [
                    "year" => "2001 - 2004",
                    "title" => "CPO Prothesist-Orthosist, Professions paramédicales de diagnostic, d'intervention et de traitement",
                    "place" => "PES MONTPLAISIR, VALENCE",
                ],
            ],
        ],
        "parcours" => [
            "id" => "parcours",
            "title" => "Parcours & Expériences",
            "items" => [
                [
                    "year" => "2022 - Présent",
                    "title" => "CEO",
                    "place" => "OrthoMater",
                ],
            ],
        ],
    ];
@endphp

@extends('brochures.layout', $brochure['layout'])

@section('content')
    @include('brochures.partials.hero', $brochure['hero'])
    @include('brochures.partials.timeline', $brochure['formation'])
    @include('brochures.partials.timeline', $brochure['parcours'])

    {{-- Ajoutez ici les sections propres à ce médecin --}}
@endsection
