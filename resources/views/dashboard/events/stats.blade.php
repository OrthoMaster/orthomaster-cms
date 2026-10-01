@extends('voyager::master')

@section('page_title', ' Bilan d’une édition')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-bar-chart"></i> {{ $edition->event_name }}
            <small>{{ $edition->edition_year }}</small>
            <span class="stats-status {{ $edition->complete_flg ? 'is-done' : 'is-coming' }}">
                {{ $edition->complete_flg ? 'Terminée' : 'À venir' }}
            </span>
        </h1>

        <a href="{{ route('dashboard.events') }}" class="btn btn-default">
            <i class="voyager-double-left"></i> <span>Retour aux congrès</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content container-fluid">
        @include('voyager::alerts')

        {{-- ============ SYNTHÈSE ============ --}}
        <div class="row stats-kpi-row">
            <div class="col-md-4 col-sm-4">
                <div class="stats-kpi">
                    <div class="stats-kpi-label">Contenus</div>
                    <div class="stats-kpi-value">
                        {{ number_format($summary['medias'], 0, ',', ' ') }}
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-4">
                <div class="stats-kpi">
                    <div class="stats-kpi-label">Sponsors</div>
                    <div class="stats-kpi-value">
                        {{ number_format($summary['sponsors'], 0, ',', ' ') }}
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-4">
                <div class="stats-kpi">
                    <div class="stats-kpi-label">Participants</div>
                    <div class="stats-kpi-value">
                        {{ number_format($summary['participants'], 0, ',', ' ') }}
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ ONGLETS ============ --}}
        <div class="stats-tabs">
            <button type="button" class="stats-tab is-active" data-tab="medias">
                <i class="voyager-play"></i> Médias
                <span class="stats-tab-count">{{ $medias->total() }}</span>
            </button>
            <button type="button" class="stats-tab" data-tab="sponsors">
                <i class="voyager-wallet"></i> Sponsors
                <span class="stats-tab-count">{{ $sponsors->count() }}</span>
            </button>
            <button type="button" class="stats-tab" data-tab="participants">
                <i class="voyager-people"></i> Participants
                <span class="stats-tab-count">{{ $participants->total() }}</span>
            </button>
            <button type="button" class="stats-tab" data-tab="settings">
                <i class="voyager-settings"></i> Paramètres
            </button>
        </div>

        {{-- ---------- Médias ---------- --}}
        <div class="stats-pane" data-pane="medias">
            <div class="row">
                <div class="col-md-10">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Contenus issus de cette édition</h3>
                        </div>
                        <div class="panel-body">
                            @if ($medias->isEmpty())
                                <p class="stats-empty">Aucun contenu rattaché à cette édition.</p>
                            @else
                                <div class="stats-list">
                                    @foreach ($medias as $media)
                                        <div class="stats-row">
                                            <div class="media-identity">
                                                <div class="media-title">{{ $media->title }}</div>
                                                <div class="media-author">{{ $media->author ?: '—' }}</div>
                                            </div>
                                            <span class="stats-spacer"></span>
                                            <div class="media-views">
                                                {{ number_format($media->views, 0, ',', ' ') }}
                                                <small>vues</small>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="stats-pagination">
                                    {{ $medias->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------- Sponsors ---------- --}}
        <div class="stats-pane" data-pane="sponsors" style="display:none">
            <div class="row">
                <div class="col-md-8">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Entreprises partenaires</h3>
                        </div>
                        <div class="panel-body">
                            @if ($sponsors->isEmpty())
                                <p class="stats-empty">Aucun sponsor enregistré.</p>
                            @else
                                <div class="stats-list">
                                    @foreach ($sponsors as $sponsor)
                                        <div class="stats-row">
                                            <span class="stats-name">{{ $sponsor->name }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------- Participants ---------- --}}
        <div class="stats-pane" data-pane="participants" style="display:none">
            <div class="row">
                <div class="col-md-8">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Personnes présentes</h3>
                        </div>
                        <div class="panel-body">
                            @if ($participants->isEmpty())
                                <p class="stats-empty">Aucun participant enregistré.</p>
                            @else
                                <div class="stats-list">
                                    @foreach ($participants as $participant)
                                        <div class="stats-row">
                                            <span class="stats-name">
                                                {{ trim(($participant->first_name ?? '') . ' ' . ($participant->name ?? '')) ?: '—' }}
                                            </span>
                                            <span class="stats-mail">{{ $participant->email }}</span>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="stats-pagination">
                                    {{ $participants->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------- Paramètres ---------- --}}
        <div class="stats-pane" data-pane="settings" style="display:none">
            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Avantage consenti aux sponsors</h3>
                        </div>
                        <div class="panel-body">
                            <dl class="stats-settings">
                                <dt>Coefficient d'affichage</dt>
                                <dd>&times; {{ $settings->ads_multiplier }}</dd>

                                <dt>Durée de l'avantage</dt>
                                <dd>
                                    {{ $settings->ads_months }}
                                    {{ $settings->ads_months > 1 ? 'mois' : 'mois' }}
                                    <span class="stats-muted">à compter de la publication de chaque contenu</span>
                                </dd>
                            </dl>

                            @unless ($settings->saved)
                                <p class="stats-empty">
                                    Ces valeurs sont celles par défaut : aucun paramétrage n'a été enregistré.
                                </p>
                            @endunless
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
    <style>
        /* --- Statut dans le titre --- */
        .stats-status {
            display: inline-block;
            margin-left: 10px;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 400;
            letter-spacing: .02em;
            vertical-align: middle;
        }

        .stats-status.is-done {
            background: #eef1f5;
            color: #8a9199;
        }

        .stats-status.is-coming {
            background: #e8f7ee;
            color: #27ae60;
        }

        /* --- Synthèse --- */
        .stats-kpi-row {
            margin-bottom: 4px;
        }

        .stats-kpi {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 16px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
        }

        .stats-kpi-label {
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .stats-kpi-value {
            font-size: 26px;
            font-weight: 700;
            color: #1d3557;
            margin-top: 6px;
            line-height: 1;
        }

        /* --- Onglets --- */
        .stats-tabs {
            display: inline-flex;
            gap: 4px;
            margin-bottom: 18px;
            padding: 4px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
        }

        .stats-tab {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 20px;
            border: none;
            border-radius: 9px;
            background: transparent;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.2;
            transition: background .15s, color .15s;
        }

        .stats-tab:hover {
            color: #1d3557;
            background: #f4f7fb;
        }

        .stats-tab.is-active,
        .stats-tab.is-active:hover {
            background: #1d3557;
            color: #fff;
            box-shadow: 0 2px 6px rgba(29, 53, 87, .28);
        }

        .stats-tab-count {
            padding: 1px 8px;
            border-radius: 10px;
            background: rgba(0, 0, 0, .07);
            font-size: 11px;
            font-weight: 600;
        }

        .stats-tab.is-active .stats-tab-count {
            background: rgba(255, 255, 255, .22);
        }

        /* --- Listes --- */
        .stats-empty {
            margin: 0;
            padding: 8px 0;
            font-size: 13px;
            color: #8a9199;
            line-height: 1.5;
        }

        .stats-list {
            border: 1px solid #e6eaf0;
            border-radius: 8px;
            overflow: hidden;
        }

        .stats-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
        }

        .stats-row+.stats-row {
            border-top: 1px solid #f2f4f7;
        }

        .stats-row:hover {
            background: #fafbfc;
        }

        .stats-name {
            font-size: 14px;
            font-weight: 600;
            color: #37474f;
        }

        .stats-mail {
            font-size: 12px;
            color: #8a9199;
        }

        .stats-spacer {
            flex: 1;
        }

        .stats-pagination {
            margin-top: 14px;
        }

        /* --- Médias --- */
        .media-identity {
            min-width: 0;
        }

        .media-title {
            font-size: 14px;
            font-weight: 600;
            color: #37474f;
        }

        .media-author {
            margin-top: 2px;
            font-size: 12px;
            color: #8a9199;
        }

        .media-views {
            flex: 0 0 auto;
            font-size: 15px;
            font-weight: 600;
            color: #1d3557;
        }

        .media-views small {
            font-size: 11px;
            font-weight: 400;
            color: #8a9199;
        }

        /* --- Paramètres --- */
        .stats-settings {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 8px 16px;
            margin: 0;
        }

        .stats-settings dt {
            font-size: 12px;
            font-weight: 400;
            color: #8a9199;
        }

        .stats-settings dd {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: #37474f;
        }

        .stats-muted {
            margin-left: 6px;
            font-size: 12px;
            font-weight: 400;
            color: #a4abb3;
        }

                /* --- Pagination --- */
        .stats-pagination nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .stats-pagination p,
        .stats-pagination .hidden {
            margin: 0;
            font-size: 12px;
            color: #8a9199;
        }

        .stats-pagination a,
        .stats-pagination span {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border: 1px solid #dfe3e9;
            border-radius: 7px;
            background: #fff;
            font-size: 13px;
            color: #37474f;
            text-decoration: none;
            transition: background .15s, border-color .15s;
        }

        .stats-pagination a:hover {
            background: #f4f7fb;
            border-color: #c6ccd4;
            color: #1d3557;
        }

        /* Bouton inactif en début ou fin de liste */
        .stats-pagination span[aria-disabled="true"],
        .stats-pagination .cursor-default {
            color: #c6ccd4;
            background: #fafbfc;
        }

        /* Page courante */
        .stats-pagination span[aria-current="page"] span {
            border-color: #1d3557;
            background: #1d3557;
            color: #fff;
        }

        .flex {
            border: 0px;
        }
    </style>
@stop

@section('javascript')
    <script>
        $(document).ready(function() {

            /* ---------- Onglets ---------- */
            function showTab(name) {
                var $tab = $('.stats-tab[data-tab="' + name + '"]');

                if (!$tab.length) return;

                $('.stats-tab').removeClass('is-active');
                $tab.addClass('is-active');

                $('.stats-pane').each(function() {
                    $(this).toggle($(this).data('pane') === name);
                });
            }

            $('.stats-tab').on('click', function() {
                var name = $(this).data('tab');

                // L'onglet courant survit à la pagination
                history.replaceState(null, '', '#' + name);
                showTab(name);
            });

            if (window.location.hash) {
                showTab(window.location.hash.slice(1));
            }
        });
    </script>
@stop
