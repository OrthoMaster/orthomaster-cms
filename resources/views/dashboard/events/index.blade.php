@extends('voyager::master')

@section('page_title', ' Congrès')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-company"></i> Congrès
        </h1>
        <a href="{{ route('voyager.events.create') }}" target="_blank" class="btn btn-success">
            <i class="voyager-plus"></i> <span>Ajouter un congrès</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            <div class="col-lg-12">

                {{-- ============ RECHERCHE ============ --}}
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <form class="row" action="{{ route('dashboard.events') }}" method="GET">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="q" id="q"
                                    value="{{ $search }}" placeholder="Rechercher un congrès">
                            </div>
                            <div class="col-md-8">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                                @if ($search !== '')
                                    <a href="{{ route('dashboard.events') }}" class="btn btn-default">
                                        Réinitialiser
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ============ LISTE ============ --}}
                @if ($events->isEmpty())
                    <div class="alert alert-warning">
                        <strong>Aucun congrès.</strong>
                        <p>Modifiez votre recherche ou créez un premier congrès.</p>
                    </div>
                @else
                    <div class="panel panel-bordered">
                        <div class="panel-body event-list">
                            @foreach ($events as $event)
                                @php $rows = $editions[$event->id] ?? collect(); @endphp

                                <div class="event-block" data-event="{{ $event->id }}">
                                    {{-- La ligne entière déploie les éditions --}}
                                    <div class="event-row {{ $rows->isEmpty() ? 'is-static' : '' }}">
                                        <button type="button" class="event-toggle" aria-expanded="false"
                                            @disabled($rows->isEmpty())>
                                            <i class="voyager-angle-right"></i>
                                        </button>

                                        <div class="event-visual">
                                            @if ($event->image)
                                                <img src="{{ $event->image }}" alt="{{ $event->name }}">
                                            @else
                                                <span class="event-visual-empty">
                                                    <i class="voyager-photo"></i>
                                                </span>
                                            @endif
                                        </div>

                                        <div class="event-identity">
                                            <div class="event-name">{{ $event->name }}</div>
                                            <div class="event-tags">
                                                <span class="event-code">{{ $event->code }}</span>
                                                <span class="event-state {{ $event->active_flg ? 'is-on' : 'is-off' }}">
                                                    {{ $event->active_flg ? 'Actif' : 'Inactif' }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="event-count">
                                            {{ $rows->count() }}
                                            {{ $rows->count() > 1 ? 'éditions' : 'édition' }}
                                        </div>

                                        {{-- Les actions ne doivent pas replier la ligne --}}
                                        <div class="event-actions" data-stop>
                                            <a href="{{ route('voyager.past-events.create', ['id_event' => $event->id]) }}"
                                                target="_blank" class="btn btn-sm btn-success">
                                                <i class="voyager-plus"></i>
                                                <span class="hidden-xs hidden-sm">Édition</span>
                                            </a>
                                            <a href="{{ route('voyager.events.edit', $event->id) }}" target="_blank"
                                                class="btn btn-sm btn-primary">
                                                <i class="voyager-edit"></i>
                                                <span class="hidden-xs hidden-sm">Modifier</span>
                                            </a>
                                        </div>
                                    </div>

                                    {{-- Éditions, masquées tant que la ligne n'est pas déployée --}}
                                    <div class="event-editions" style="display:none">
                                        @if ($rows->isEmpty())
                                            <p class="edition-empty">Aucune édition enregistrée.</p>
                                        @else
                                            <div class="edition-card">
                                                @foreach ($rows as $edition)
                                                    @php
                                                        $done = (bool) $edition->complete_flg;
                                                        $active = (bool) $edition->active_flg;
                                                    @endphp

                                                    <div class="edition-row">
                                                        <span class="edition-year">{{ $edition->edition_year }}</span>

                                                        <span class="edition-badge {{ $done ? 'is-done' : 'is-coming' }}">
                                                            {{ $done ? 'Terminée' : 'À venir' }}
                                                        </span>

                                                        <span class="edition-badge {{ $active ? 'is-on' : 'is-off' }}">
                                                            {{ $active ? 'Active' : 'Inactive' }}
                                                        </span>

                                                        <span class="edition-spacer"></span>

                                                                                                                @if ($done)
                                                            {{-- L'édition close se consulte plutôt qu'elle ne se prépare --}}
                                                            <a href="{{ route('dashboard.events.prepare', $edition->id) }}"
                                                                class="btn btn-sm btn-default">
                                                                <i class="voyager-tools"></i>
                                                                <span class="hidden-xs hidden-sm">Préparer</span>
                                                            </a>
                                                        @else
                                                            <a href="{{ route('dashboard.events.prepare', $edition->id) }}"
                                                                class="btn btn-sm btn-warning">
                                                                <i class="voyager-tools"></i>
                                                                <span class="hidden-xs hidden-sm">Préparer</span>
                                                            </a>
                                                        @endif

                                                        <a href="{{ route('dashboard.events.stats', $edition->id) }}"
                                                            class="btn btn-sm btn-default">
                                                            <i class="voyager-list"></i>
                                                            <span class="hidden-xs hidden-sm">Statistiques</span>
                                                        </a>

                                                        <a href="{{ route('voyager.past-events.edit', $edition->id) }}"
                                                            target="_blank" class="btn btn-sm btn-primary">
                                                            <i class="voyager-edit"></i>
                                                            <span class="hidden-xs hidden-sm">Modifier</span>
                                                        </a>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        .event-list {
            padding: 0;
        }

        .event-block+.event-block {
            border-top: 1px solid #eceff3;
        }

        /* --- Ligne du congrès --- */
        .event-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            cursor: pointer;
        }

        .event-row.is-static {
            cursor: default;
        }

        .event-row:hover:not(.is-static) {
            background: #fafbfc;
        }

        .event-block.is-open .event-row {
            background: #f7fbfe;
        }

        .event-toggle {
            width: 28px;
            height: 28px;
            padding: 0;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: #8a9199;
            transition: background .15s, transform .15s;
        }

        .event-toggle:disabled {
            opacity: .3;
        }

        .event-block.is-open .event-toggle {
            transform: rotate(90deg);
        }

        /* Le visuel est cadré au carré : les affiches ont des formats variés */
        .event-visual {
            flex: 0 0 auto;
            width: 52px;
            height: 52px;
            border-radius: 8px;
            overflow: hidden;
            background: #f4f6f9;
            border: 1px solid #eceff3;
        }

        .event-visual img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .event-visual-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: #c6ccd4;
        }

        .event-identity {
            flex: 1;
            min-width: 0;
        }

        .event-name {
            font-size: 15px;
            font-weight: 600;
            color: #37474f;
        }

        .event-tags {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
        }

        .event-code {
            display: inline-block;
            padding: 1px 9px;
            border: 1px solid #dfe3e9;
            border-radius: 11px;
            font-size: 11px;
            font-weight: 400;
            letter-spacing: .06em;
            color: #8a9199;
            background: #fafbfc;
        }

        .event-state {
            display: inline-block;
            padding: 1px 9px;
            border-radius: 11px;
            font-size: 11px;
            font-weight: 400;
        }

        .event-state.is-on {
            background: #e8f7ee;
            color: #27ae60;
        }

        .event-state.is-off {
            background: #fdeceb;
            color: #c0392b;
        }

        .event-count {
            flex: 0 0 auto;
            font-size: 12px;
            color: #8a9199;
        }

        .event-actions {
            flex: 0 0 auto;
            display: flex;
            gap: 6px;
        }

        /* --- Éditions --- */
        .event-editions {
            padding: 0 16px 16px 74px;
        }

        .edition-empty {
            margin: 0;
            padding: 12px 0;
            font-size: 13px;
            color: #8a9199;
        }

        .edition-card {
            background: #fff;
            border: 1px solid #e6eaf0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .03);
        }

        .edition-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
        }

        .edition-row+.edition-row {
            border-top: 1px solid #f2f4f7;
        }

        .edition-row:hover {
            background: #fafbfc;
        }

        .edition-year {
            font-size: 14px;
            font-weight: 600;
            color: #37474f;
        }

        .edition-spacer {
            flex: 1;
        }

        .edition-badge {
            display: inline-block;
            padding: 2px 11px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 400;
            letter-spacing: .02em;
        }

        .edition-badge.is-done {
            background: #eef1f5;
            color: #8a9199;
        }

        .edition-badge.is-coming {
            background: #e8f7ee;
            color: #27ae60;
        }

        .edition-badge.is-on {
            background: #eaf4fd;
            color: #2980b9;
        }

        .edition-badge.is-off {
            background: #fdeceb;
            color: #c0392b;
        }

        .btn {
            margin-top: auto;
        }
    </style>
@stop

@section('javascript')
    <script>
        $(document).ready(function() {

            /* ---------- Déploiement des éditions ---------- */
            $('.event-row').on('click', function(e) {
                // Un clic sur un bouton d'action ne doit pas replier la ligne
                if ($(e.target).closest('[data-stop]').length) {
                    return;
                }

                var $block = $(this).closest('.event-block'),
                    $editions = $block.find('.event-editions'),
                    open = !$block.hasClass('is-open');

                if ($(this).hasClass('is-static')) {
                    return;
                }

                $block.toggleClass('is-open', open);
                $editions.slideToggle(160);
                $block.find('.event-toggle').attr('aria-expanded', open);
            });

        });
    </script>
@stop
