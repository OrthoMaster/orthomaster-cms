@extends('voyager::master')

@section('page_title', ' Nouveau média')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-plus"></i> Nouveau média
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        @include('voyager::alerts')

        <form action="{{ route('medias.store') }}" method="POST"
              enctype="multipart/form-data" id="media-form">
            @csrf
            @include('dashboard.medias._form', ['submitLabel' => 'Créer'])
        </form>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/editorjs/editor.css') }}">
    <link rel="stylesheet" href="{{ asset('css/editorjs/video.css') }}">
    <link rel="stylesheet" href="{{ asset('css/editorjs/video-chapters.css') }}">
    <style>
        /* --- Boutons d'action fixés en bas à droite --- */
        .action-wrapper-absolute {
            position: fixed;
            z-index: 9999;
            right: 40px;
            bottom: 30px;
            display: flex;
            gap: 8px;
            padding: 10px 12px;
            background: rgba(255, 255, 255, .96);
            border: 1px solid #e3e6ec;
            border-radius: 8px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .12);
        }

        .action-wrapper-absolute .btn { margin: 0; }
    </style>
@stop

@section('javascript')
    @include('dashboard.medias._form_scripts')
@stop