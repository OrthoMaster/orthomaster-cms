@extends('voyager::master')

@section('page_title', ' Modifier le média')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-edit"></i> Modifier le média <small>#{{ $media->id_content }}</small>
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        @include('voyager::alerts')

        @if (is_null($video))
            <div class="alert alert-info">
                <p style="margin-bottom:12px">
                    <strong>Ce média n'est lié à aucune vidéo.</strong>
                    Seuls les champs du contenu sont modifiables. Créez une nouvelle version
                    pour gérer le code de diffusion, la vignette et les chapitres.
                </p>
                <form action="{{ route('medias.create-version', $media->id_content) }}" method="POST"
                      style="display:inline">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="voyager-plus"></i> Créer une nouvelle version
                    </button>
                </form>
            </div>
        @endif

        <form action="{{ route('medias.update', $media->id_content) }}" method="POST"
              enctype="multipart/form-data" id="media-form">
            @csrf
            @method('PUT')
            @include('dashboard.medias._form', ['submitLabel' => 'Enregistrer'])
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

        /* Évite que le dernier panneau passe sous la barre d'actions */
        .page-content.edit-add { padding-bottom: 90px; }

        /* --- Interrupteur DEV mode --- */
        .dev-mode-toggle { display: inline-flex; align-items: center; gap: 10px; }

        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }

        .slider {
            position: absolute; cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc; transition: .4s;
        }

        .slider:before {
            position: absolute; content: "";
            height: 18px; width: 18px; left: 3px; bottom: 3px;
            background-color: #fff; transition: .4s;
        }

        input:checked + .slider { background-color: #2196F3; }
        input:focus + .slider { box-shadow: 0 0 1px #2196F3; }
        input:checked + .slider:before { transform: translateX(26px); }

        .slider.round { border-radius: 24px; }
        .slider.round:before { border-radius: 50%; }

        .toggle-label { font-size: 13px; font-weight: 500; }

        /* --- Éditeur --- */
        .ce-header { color: #37474f; }

        .cdx-notifies {
            z-index: 99999 !important;
            right: 30px !important;
            top: 50px !important;
            left: auto !important;
        }

        .cdx-button {
            background: #f9f9f9;
            border: 1px solid #f0f0f0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
            font-size: 14px;
        }

        .image-tool--withBackground .image-tool__image { background: none !important; }
        .image-tool__caption[contentEditable="true"][data-placeholder]::before { color: #bebebe !important; }
        .ce-popover__item[data-item-name="withBorder"] { display: none !important; }
        .ce-popover__item[data-item-name="stretched"] { display: none !important; }

        #codePreview pre {
            max-height: 600px;
            overflow: auto;
            font-size: 12px;
        }
    </style>
@stop

@section('javascript')
    <script src="{{ asset('js/editorjs/editor.js') }}"></script>
    <script src="{{ asset('js/editorjs/header.js') }}"></script>
    <script src="{{ asset('js/editorjs/list.js') }}"></script>
    <script src="{{ asset('js/editorjs/image.js') }}"></script>
    <script src="{{ asset('js/editorjs/paragraph.js') }}"></script>
    <script src="{{ asset('js/editorjs/media-video.js') }}"></script>
    <script src="{{ asset('js/editorjs/media-chapters.js') }}"></script>

    @include('dashboard.medias._form_scripts')
@stop