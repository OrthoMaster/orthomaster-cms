@extends('voyager::master')

@section('page_title', ' ' . $board->title)

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-chat"></i> {{ $board->title }}
        </h1>
        <span class="label" id="status-label"></span>
        <button type="button" class="btn btn-success" id="start"></button>
        <button type="button" class="btn btn-danger" id="stop">Arrêter</button>
        <a href="{{ route('dashboard.congres.edit', $board) }}" class="btn btn-default">Modifier</a>
        <a href="{{ route('dashboard.congres') }}" class="btn btn-default">Liste</a>
    </div>
@stop

@section('css')
    <style>
        /* --- Liens --- */
        .cg-details { margin-bottom: 12px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .cg-details summary { padding: 8px 14px; font-weight: 600; cursor: pointer; }
        .cg-links { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; padding: 4px 14px 14px; }
        .cg-links label { font-weight: 600; }
        .cg-link { display: flex; flex-wrap: wrap; gap: 8px; }
        .cg-link .form-control { flex: 1 1 100%; min-width: 0; }
        .cg-link .btn { margin: 0; padding: 4px 10px; font-size: 13px; }

        /* --- Colonnes --- */
        /* Grille et non ligne : six colonnes ne tiennent pas en largeur. Chaque liste défile seule. */
        #columns { display: grid; grid-template-columns: repeat(var(--cols, 3), minmax(0, 1fr)); gap: 16px; align-items: start; padding-bottom: 12px; }
        .cg-col { min-width: 0; background: #fff; border-radius: 8px; padding: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .cg-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 6px 12px; margin-bottom: 10px; border-radius: 999px; color: #1B1F2A; font-weight: 600; }
        .cg-list { min-height: 72px; max-height: var(--list-height, 70vh); overflow-y: auto; padding-right: 4px; }
        .cg-empty { color: #6B7280; padding: 8px; }

        /* --- Cartes --- */
        .cg-card { position: relative; padding: 8px 10px; margin-bottom: 8px; border: 1px solid #E3E6EC; border-radius: 6px; background: #F7F8FA; cursor: grab; }
        .cg-card.on-screen { background: #fff; border-left-width: 5px; }
        .cg-card.dragging { opacity: .4; }
        .cg-card.drop-before { box-shadow: 0 -3px 0 #2D55AF; }
        .cg-text { padding-right: 52px; color: #1B1F2A; line-height: 20px; overflow-wrap: anywhere; }
        .cg-meta { display: flex; justify-content: space-between; gap: 8px; margin-top: 4px; color: #6B7280; font-size: 12px; line-height: 16px; }

        /* --- Boutons d'une carte --- */
        .cg-tools { position: absolute; top: 6px; right: 6px; display: flex; gap: 4px; }
        .cg-icon { width: 24px; height: 24px; padding: 0; border: 0; border-radius: 4px; background: transparent; color: #6B7280; font-size: 16px; line-height: 24px; text-align: center; cursor: pointer; }
        .cg-icon:hover, .cg-icon:focus-visible { background: #E3E6EC; color: #1B1F2A; }
        .cg-delete:hover, .cg-delete:focus-visible { background: #FDECEA; color: #C62828; }
        .cg-delete.confirm { width: auto; padding: 0 8px; background: #E53935; color: #fff; font-size: 12px; font-weight: 600; }
        .cg-separator { margin: 10px 0; padding-top: 6px; border-top: 2px dashed #CFD4DE; color: #6B7280; font-size: 12px; }

        /* --- Petits écrans --- */
        @media (max-width: 1100px) {
            .cg-links, #columns { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 700px) {
            .cg-links, #columns { grid-template-columns: minmax(0, 1fr); }
        }
    </style>
@stop

@section('content')
    <div class="page-content container-fluid">
        {{-- Replié en séance : six thèmes doivent tenir sur un écran sans défiler. --}}
        <details class="cg-details" @if ($board->status === 'draft') open @endif>
            <summary>Liens de projection et du public</summary>
            <div class="cg-links">
                <div>
                    <label for="screen-url">Lien de projection</label>
                    <div class="cg-link">
                        <input type="text" class="form-control" id="screen-url" readonly value="{{ $board->screenUrl() }}">
                        <button type="button" class="btn btn-default" data-copy="screen-url">Copier</button>
                        <a class="btn btn-default" id="screen-open" href="{{ $board->screenUrl() }}" target="_blank" rel="noopener">Ouvrir</a>
                        <button type="button" class="btn btn-default" id="screen-reset">Générer un nouveau lien</button>
                    </div>
                </div>
                <div>
                    <label for="reader-url">Lien du public (QR affiché sur l'écran)</label>
                    <div class="cg-link">
                        <input type="text" class="form-control" id="reader-url" readonly value="{{ $board->readerUrl() }}">
                        <button type="button" class="btn btn-default" data-copy="reader-url">Copier</button>
                    </div>
                </div>
            </div>
        </details>

        <div id="columns"></div>
    </div>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {
            const POLL_MS = 3000;
            const CONFIRM_MS = 3000;
            const URLS = {
                data: @json(route('dashboard.congres.live.data', $board)),
                start: @json(route('dashboard.congres.start', $board)),
                stop: @json(route('dashboard.congres.stop', $board)),
                arrange: @json(route('dashboard.congres.arrange', $board)),
                link: @json(route('dashboard.congres.screen-link', $board)),
                question: @json(url('admin/dashboard/congres/' . $board->id . '/questions')),
            };
            const CSRF = @json(csrf_token());
            const STATUS = {
                draft: ['Brouillon', 'label-warning', 'Démarrer'],
                live: ['En cours', 'label-success', ''],
                closed: ['Terminée', 'label-default', 'Rouvrir'],
            };

            let revision = '';
            let themes = [];
            let dragged = null;
            let confirming = null;

            function send(url, method, body) {
                return fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: body ? JSON.stringify(body) : undefined,
                }).then((response) => {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                });
            }

            function fail() {
                toastr.error('Action impossible. La page va se recharger.');
                revision = '';
                load();
            }

            /* --- Affichage --- */

            function paintStatus(status) {
                const [text, css, action] = STATUS[status];
                $('#status-label').attr('class', 'label ' + css).text(text);
                $('#start').text(action).toggle(status !== 'live');
                $('#stop').toggle(status === 'live');
            }

            function card(question, index, theme) {
                const onScreen = index < theme.layout.capacity;
                const el = $('<div class="cg-card" draggable="true">')
                    .attr('data-id', question.id)
                    .toggleClass('on-screen', onScreen);
                if (onScreen) {
                    el.css('border-left-color', theme.color);
                }

                el.append($('<div class="cg-text">').text(question.text));
                el.append(
                    $('<div class="cg-meta">')
                        .append($('<span>').text(question.author))
                        .append($('<span>').text('n° ' + (index + 1)))
                );

                const tools = $('<div class="cg-tools">');
                if (index > 0) {
                    tools.append('<button type="button" class="cg-icon cg-top" title="Mettre en tête" aria-label="Mettre en tête">&uarr;</button>');
                }
                tools.append('<button type="button" class="cg-icon cg-delete" title="Supprimer" aria-label="Supprimer">&times;</button>');
                el.append(tools);

                return el;
            }

            function paintColumns() {
                const columns = $('#columns');

                // Le contenu est reconstruit : sans cela, chaque nouvelle question remonterait les listes en haut.
                const scrolls = {};
                columns.find('.cg-col').each(function () {
                    scrolls[$(this).attr('data-theme')] = $(this).find('.cg-list').scrollTop();
                });
                columns.empty();

                // Même disposition que l'écran : au-delà de quatre thèmes, deux rangées de trois.
                const count = themes.length;
                columns[0].style.setProperty('--cols', count >= 5 ? 3 : Math.max(count, 1));
                columns[0].style.setProperty('--list-height', count >= 5 ? 'max(200px, calc(50vh - 190px))' : 'max(260px, calc(100vh - 280px))');

                themes.forEach((theme) => {
                    const col = $('<div class="cg-col">').attr('data-theme', theme.id);
                    col.append(
                        $('<div class="cg-head">').css('background', theme.color)
                            .append($('<span>').text(theme.name))
                            .append($('<span>').text(theme.total))
                    );

                    const list = $('<div class="cg-list">');
                    if (theme.questions.length === 0) {
                        list.append('<div class="cg-empty">Aucune question</div>');
                    }
                    theme.questions.forEach((question, index) => {
                        if (index === theme.layout.capacity) {
                            list.append('<div class="cg-separator">En attente, hors écran</div>');
                        }
                        list.append(card(question, index, theme));
                    });

                    columns.append(col.append(list));
                    list.scrollTop(scrolls[theme.id] || 0);
                });
            }

            /* --- Données --- */

            function load() {
                // Pas de relecture sous la souris : la carte déplacée disparaîtrait.
                if (dragged || confirming) {
                    return Promise.resolve();
                }

                return fetch(URLS.data + '?rev=' + encodeURIComponent(revision), { headers: { Accept: 'application/json' } })
                    .then((response) => {
                        if (response.status === 200) {
                            return response.json();
                        }
                        return null;
                    })
                    .then((data) => {
                        if (data && !dragged && !confirming) {
                            revision = data.revision;
                            themes = data.themes;
                            paintStatus(data.status);
                            paintColumns();
                        }
                    })
                    .catch(() => {});
            }

            function reload() {
                revision = '';
                return load();
            }

            function idsOf(list) {
                return list.find('.cg-card').map(function () { return Number($(this).attr('data-id')); }).get();
            }

            function arrange(themeId, ids) {
                send(URLS.arrange, 'POST', { theme_id: themeId, ids }).then(reload).catch(fail);
            }

            /* --- Glisser-déposer --- */

            $('#columns').on('dragstart', '.cg-card', function (event) {
                dragged = this;
                event.originalEvent.dataTransfer.effectAllowed = 'move';
                event.originalEvent.dataTransfer.setData('text/plain', $(this).attr('data-id'));
                $(this).addClass('dragging');
            });

            $('#columns').on('dragover', '.cg-col', function (event) {
                event.preventDefault();
                $('.drop-before').removeClass('drop-before');
                const target = $(event.target).closest('.cg-card');
                if (target.length && target[0] !== dragged) {
                    target.addClass('drop-before');
                }
            });

            $('#columns').on('drop', '.cg-col', function (event) {
                event.preventDefault();
                const target = $(event.target).closest('.cg-card');
                const list = $(this).find('.cg-list');

                if (target.length && target[0] !== dragged) {
                    target.before(dragged);
                } else if (!target.length) {
                    list.append(dragged);
                }

                const themeId = Number($(this).attr('data-theme'));
                const ids = idsOf(list);
                finishDrag();
                arrange(themeId, ids);
            });

            function finishDrag() {
                $(dragged).removeClass('dragging');
                $('.drop-before').removeClass('drop-before');
                dragged = null;
            }

            $('#columns').on('dragend', '.cg-card', finishDrag);

            /* --- Actions sur une carte --- */

            $('#columns').on('click', '.cg-top', function () {
                const list = $(this).closest('.cg-list');
                const id = Number($(this).closest('.cg-card').attr('data-id'));
                const ids = idsOf(list).filter((other) => other !== id);
                arrange(Number($(this).closest('.cg-col').attr('data-theme')), [id].concat(ids));
            });

            // Suppression définitive : deux clics, sans fenêtre de confirmation qui ralentirait la séance.
            $('#columns').on('click', '.cg-delete', function () {
                const button = $(this);
                const id = button.closest('.cg-card').attr('data-id');

                if (confirming === id) {
                    confirming = null;
                    send(URLS.question + '/' + id, 'DELETE').then(reload).catch(fail);
                    return;
                }

                confirming = id;
                button.addClass('confirm').text('Supprimer ?');
                setTimeout(() => {
                    if (confirming === id) {
                        confirming = null;
                        button.removeClass('confirm').html('&times;');
                    }
                }, CONFIRM_MS);
            });

            /* --- Séance et liens --- */

            $('#start').on('click', () => send(URLS.start, 'POST').then(reload).catch(fail));
            $('#stop').on('click', () => send(URLS.stop, 'POST').then(reload).catch(fail));

            $('[data-copy]').on('click', function () {
                const input = document.getElementById($(this).attr('data-copy'));
                input.select();
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(input.value);
                } else {
                    document.execCommand('copy');
                }
                toastr.success('Lien copié');
            });

            $('#screen-reset').on('click', function () {
                if (!window.confirm('L\'écran qui utilise le lien actuel affichera « Lien désactivé ». Continuer ?')) {
                    return;
                }
                send(URLS.link, 'POST').then((data) => {
                    $('#screen-url').val(data.screen_url);
                    $('#screen-open').attr('href', data.screen_url);
                    toastr.success('Nouveau lien généré');
                }).catch(fail);
            });

            load();
            setInterval(load, POLL_MS);
        });
    </script>
@stop
