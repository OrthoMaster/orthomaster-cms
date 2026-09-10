@if (request()->boolean('embed'))
    {{-- Page ouverte dans une fenêtre : seul le formulaire est utile --}}
    <style>
        .app-container>.side-menu,
        .app-container>.app-navbar,
        .side-menu,
        .navbar,
        .app-footer,
        .page-title,
        #adminmenu {
            display: none !important;
        }

        .app-container .side-body,
        .side-body {
            margin-left: 10px !important;
        }

        .app-container,
        .app-container .container-fluid {
            padding: 0 !important;
            margin: 0 !important;
        }

        .page-content {
            padding-top: 0px !important;
        }

        body {
            background: #fff;
        }
    </style>

    <script>
        (function() {
            document.addEventListener('DOMContentLoaded', function() {
                // Le champ voyage avec le formulaire : la redirection le conserve
                document.querySelectorAll('form').forEach(function(form) {
                    if (form.querySelector('input[name="embed"]')) return;

                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'embed';
                    input.value = '1';
                    form.appendChild(input);
                });

                // Le retour à la liste n'a pas de sens ici : la fenêtre se referme
                document.querySelectorAll('a.btn-default, a.btn-cancel').forEach(function(link) {
                    var label = (link.textContent || '').trim().toLowerCase();

                    if (label !== 'annuler' && label !== 'retour') return;

                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        window.parent.postMessage({
                            source: 'linked-content',
                            closed: true
                        }, '*');
                    });
                });
            });
        })();
    </script>
@endif
