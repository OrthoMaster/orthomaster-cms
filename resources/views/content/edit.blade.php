@extends('voyager::master')

@section('page_title', 'Les contenus')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-character"></i> Le contenu
        </h1>
        <!-- Dev Mode Toggle -->
        <div class="text-right">
            <div class="dev-mode-toggle">
                <label class="switch">
                    <input type="checkbox" id="devModeToggle">
                    <span class="slider round"></span>
                </label>
                <span class="toggle-label">DEV mode</span>
            </div>
        </div>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        <div class="row">
            <div class="col-md-2" id="leftColumn"></div>
            <div class="col-md-8" id="editorColumn">
                <div class="panel panel-bordered">
                    <div class="panel-heading editor-title">
                        <small>{{ $lesson->training->name }}</small>
                        <h4>{{ $lesson->title }}</h4>
                    </div>
                    <div class="panel-body">
                        <div id="editorjs"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4" id="previewColumn" style="display: none;">
                <div id="codePreview"></div>
            </div>
        </div>
    </div>
    <div class="action-wrapper-absolute">
        <button class="btn btn-success" onclick="saveContent()" class="save-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" style="width: 15px; height: 15px">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
            </svg>
            <span>Sauvegarder</span>
        </button>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/editorjs/editor.css') }}">
    <link rel="stylesheet" href="{{ asset('css/editorjs/quiz-choice.css') }}">
    <link rel="stylesheet" href="{{ asset('css/editorjs/text-variant-tune.css') }}">
    <link rel="stylesheet" href="{{ asset('css/editorjs/video.css') }}">
    <style>
        .ce-header {
            color: #37474f;
        }

        .action-wrapper-absolute {
            position: fixed;
            z-index: 99999;
            right: 60px;
            bottom: 50px;
        }

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

        .image-tool--withBackground .image-tool__image {
            background: none !important;
        }

        .image-tool__caption[contentEditable="true"][data-placeholder]::before {
            color: #bebebe !important;
        }

        .ce-popover__item[data-item-name="withBorder"] {
            display: none !important;
        }

        .ce-popover__item[data-item-name="stretched"] {
            display: none !important;
        }

        /* Dev Mode Toggle Styles */
        .dev-mode-toggle {
            display: inline;
            align-items: center;
            gap: 10px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            -webkit-transition: .4s;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            -webkit-transition: .4s;
            transition: .4s;
        }

        input:checked+.slider {
            background-color: #2196F3;
        }

        input:focus+.slider {
            box-shadow: 0 0 1px #2196F3;
        }

        input:checked+.slider:before {
            -webkit-transform: translateX(26px);
            -ms-transform: translateX(26px);
            transform: translateX(26px);
        }

        .slider.round {
            border-radius: 24px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        .toggle-label {
            font-size: 14px;
            font-weight: 500;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            #previewColumn {
                margin-top: 20px;
            }
        }
    </style>
@stop

@section('javascript')
    <script src="{{ asset('js/editorjs/editor.js') }}"></script>
    <script src="{{ asset('js/editorjs/header.js') }}"></script>
    <script src="{{ asset('js/editorjs/list.js') }}"></script>
    <script src="{{ asset('js/editorjs/image.js') }}"></script>
    <script src="{{ asset('js/editorjs/quote.js') }}"></script>
    <script src="{{ asset('js/editorjs/paragraph.js') }}"></script>
    <script src="{{ asset('js/editorjs/quiz-choice.js') }}"></script>
    <script src="{{ asset('js/editorjs/text-variant-tune.js') }}"></script>
    <script src="{{ asset('js/editorjs/video.js') }}"></script>

    <script>
        // Dev Mode Toggle functionality
        function toggleDevMode() {
            const devModeToggle = document.getElementById('devModeToggle');
            const previewColumn = document.getElementById('previewColumn');
            const editorColumn = document.getElementById('editorColumn');
            const leftColumn = document.getElementById('leftColumn');

            if (devModeToggle.checked) {
                // Show preview column and adjust editor column
                previewColumn.style.display = 'block';
                leftColumn.classList.remove('col-md-2');
            } else {
                // Hide preview column and make editor full width
                previewColumn.style.display = 'none';
                leftColumn.classList.add('col-md-2');
            }
        }

        // Initialize dev mode toggle
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listener
            devModeToggle.addEventListener('change', function() {
                toggleDevMode();
            });
        });

        // Initialize EditorJS
        const editor = new EditorJS({
            holder: 'editorjs',
            placeholder: "Commencez à écrire ...",
            autofocus: true,
            tools: {
                textVariant: TextVariantTune,
                header: {
                    class: Header,
                    config: {
                        placeholder: 'Titre',
                        levels: [1, 2, 3, 4],
                        defaultLevel: 3
                    }
                },
                paragraph: {
                    class: Paragraph,
                    inlineToolbar: ['bold', 'italic'],
                    tunes: ['textVariant'],
                },
                list: {
                    class: List,
                    inlineToolbar: true,
                    config: {
                        placeholder: 'Liste',
                        defaultStyle: 'unordered'
                    }
                },
                image: {
                    class: ImageTool,
                    inlineToolbar: true,
                    config: {
                        features: {
                            border: false,
                            caption: 'optional',
                            stretch: false
                        },
                        captionPlaceholder: "Écrivez une légende (facultatif)",
                        uploader: {
                            uploadByFile(file) {
                                const formData = new FormData();
                                formData.append('image', file);
                                formData.append('id', {{ $lesson->id }});

                                return fetch("{{ route('upload.image.s3.lesson') }}", {
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                            'Accept': 'application/json'
                                        },
                                        body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            return {
                                                success: 1,
                                                file: {
                                                    url: data.file.url,
                                                    name: data.file.name || file.name,
                                                    size: file.size
                                                }
                                            };
                                        } else {
                                            throw new Error(data.message || 'Upload failed');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Upload error:', error);
                                        return {
                                            success: 0,
                                            error: error.message
                                        };
                                    });
                            }
                        }
                    }
                },
                video: {
                    class: VideoBlock,
                    config: {
                        apiEndpoint: '/api',
                    }
                },
                quizSelect: QuizChoice,
            },
            data: JSON.parse(@json($block->content)),
            onReady: () => {
                updateCodePreview('Modifiez pour afficher');
            },
            onChange: (api, event) => {
                console.log('Content changed', event);
                editor.save().then((outputData) => {
                    updateCodePreview(outputData);
                })
            },
            i18n: {
                messages: {
                    "ui": {
                        "blockTunes": {
                            "toggler": {
                                "Click to tune": "Cliquer pour configurer",
                                "or drag to move": "ou glisser pour déplacer"
                            }
                        },
                        "inlineToolbar": {
                            "converter": {
                                "Convert to": "Convertir en"
                            }
                        },
                        "toolbar": {
                            "toolbox": {
                                "Add": "Ajouter"
                            }
                        },
                        "popover": {
                            "Filter": "Filtrer",
                            "Nothing found": "Aucun résultat",
                            "Convert to": "Convertir en"
                        }
                    },
                    "blockTunes": {
                        "delete": {
                            "Delete": "Effacer",
                            "Click to delete": "Confirmer",
                        },
                        "moveUp": {
                            "Move up": "Monter"
                        },
                        "moveDown": {
                            "Move down": "Descendre"
                        },
                        "textVariant": {
                            "Call-out": "Mise en évidence",
                            "Citation": "Citation",
                            "Details": "Détails"
                        },
                    },
                    "toolNames": {
                        "Text": "Texte",
                        "Heading": "Titre",
                        "List": "Liste",
                        "Bold": "Gras",
                        "Italic": "Italique",
                    },
                    "tools": {
                        "header": {
                            "Header": "Titre"
                        },
                        "image": {
                            "With background": "Centre",
                            "Select an Image": "Sélectionnez une image",
                        },
                        "list": {
                            "Ordered": "Numérotée",
                            "Unordered": "À puces",
                        },
                        "paragraph": {
                            "Enter something": "Saisissez votre texte",
                        }
                    }
                }
            },
        });

        // Save content function
        function saveContent() {
            editor.save().then((outputData) => {
                console.log('Data: ', outputData);

                fetch("{{ route('content.update', request()->route('id')) }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            content: outputData,
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        toastr.success('Successfully');
                        console.log(data);
                    })
                    .catch(error => {
                        toastr.error('Erreur: ', error);
                        console.error('Error:', error);
                    });

            }).catch((error) => {
                toastr.error('Erreur: ', error);
                console.log('Saving failed: ', error);
            });
        }

        // Preview
        function updateCodePreview(data) {

            console.log(data);
            const codePreview = document.getElementById('codePreview');
            const formattedCode = JSON.stringify(data, null, 2);
            codePreview.innerHTML =
                `<pre class="code-block"><code class="language-json">${escapeHtml(formattedCode)}</code></pre>`;
        }

        // Escape HTML function
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
@stop
