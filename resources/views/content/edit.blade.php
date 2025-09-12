@extends('voyager::master')

@section('page_title', 'Les contenus')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-character"></i> Le contenu
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <div id="editorjs"></div>
                    </div>
                </div>

                <button class="btn btn-primary" onclick="saveContent()" class="save-btn">Save</button>
            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/editorjs/editor.css') }}">
    <link rel="stylesheet" href="{{ asset('css/editorjs/quiz-choice.css') }}">
    <style>

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
    <script>
        // Initialize EditorJS
        const editor = new EditorJS({
            holder: 'editorjs',
            placeholder: 'Start writing your content here...',
            tools: {
                header: {
                    class: Header,
                    config: {
                        placeholder: 'Enter a header',
                        levels: [1, 2, 3, 4],
                        defaultLevel: 3
                    }
                },
                paragraph: {
                    class: Paragraph,
                    inlineToolbar: ['bold', 'italic'],
                },
                list: {
                    class: List,
                    inlineToolbar: true,
                    config: {
                        defaultStyle: 'unordered'
                    }
                },
                quizSelect: QuizChoice,
                // quote: {
                //     class: Quote,
                //     inlineToolbar: true,
                //     shortcut: 'CMD+SHIFT+O',
                //     config: {
                //         quotePlaceholder: 'Enter a quote',
                //         captionPlaceholder: 'Quote\'s author',
                //     }
                // },
                // image: {
                //     class: ImageTool,
                //     config: {
                //         endpoints: {
                //             byFile: '/upload-image',
                //             byUrl: '/upload-image-url'
                //         }
                //     }
                // }
            },
            data: {},
            onReady: () => {
                console.log('Editor.js is ready to work!');
            },
            onChange: (api, event) => {
                console.log('Content changed', event);
            }
        });

        // Save content function
        function saveContent() {
            editor.save().then((outputData) => {
                console.log('Article data: ', outputData);

                // fetch('/save-content', {
                //     method: 'POST',
                //     headers: {
                //         'Content-Type': 'application/json',
                //         'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                //         'Accept': 'application/json'
                //     },
                //     body: JSON.stringify({
                //         content: outputData,
                //         title: 'My Article'
                //     })
                // })
                // .then(response => response.json())
                // .then(data => {
                //     if (data.success) {
                //         showStatus('Content saved successfully!', 'success');
                //     } else {
                //         showStatus('Failed to save content!', 'error');
                //     }
                // })
                // .catch(error => {
                //     console.error('Error:', error);
                //     showStatus('Error saving content!', 'error');
                // });

            }).catch((error) => {
                console.log('Saving failed: ', error);
                showStatus('Error preparing content for save!', 'error');
            });
        }
    </script>
@stop
