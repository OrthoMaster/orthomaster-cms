@extends('voyager::master')

@php
    $locked = (bool) $edition->complete_flg;
@endphp

@section('page_title', ' Préparer une édition')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-tools"></i> {{ $edition->event_name }}
            <small>{{ $edition->edition_year }}</small>
            <span class="prep-status {{ $locked ? 'is-done' : 'is-coming' }}">
                {{ $locked ? 'Terminée' : 'À venir' }}
            </span>
        </h1>

        @unless ($locked)
            <button type="button" class="btn btn-success" id="btn-complete">
                <i class="voyager-check"></i> <span>Terminer</span>
            </button>
        @endunless

        <a href="{{ route('dashboard.events') }}" class="btn btn-default">
            <i class="voyager-double-left"></i> <span>Retour aux congrès</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content container-fluid">
        @include('voyager::alerts')

        @if ($locked)
            <div class="alert alert-info">
                <strong>Édition terminée.</strong>
                Les sponsors, les participants et les paramètres ne sont plus modifiables.
            </div>
        @endif

        {{-- ============ ONGLETS ============ --}}
        <div class="prep-tabs">
            <button type="button" class="prep-tab is-active" data-tab="sponsors">
                <i class="voyager-wallet"></i> Sponsors
                <span class="prep-tab-count">{{ $sponsors->count() }}</span>
            </button>
            <button type="button" class="prep-tab" data-tab="participants">
                <i class="voyager-people"></i> Participants
                <span class="prep-tab-count">{{ $participants->total() }}</span>
            </button>
            <button type="button" class="prep-tab" data-tab="settings">
                <i class="voyager-settings"></i> Paramètres
            </button>
        </div>

        {{-- ---------- Sponsors ---------- --}}
        <div class="prep-pane" data-pane="sponsors">
            <div class="row">
                <div class="col-md-8">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Entreprises partenaires</h3>
                        </div>
                        <div class="panel-body">
                            @unless ($locked)
                                <form class="prep-add"
                                    action="{{ route('dashboard.events.prepare.sponsors.add', $edition->id) }}" method="POST">
                                    @csrf
                                    <select class="form-control" name="ads_company_id" id="sponsor-select" required></select>
                                    <button type="submit" class="btn btn-success">
                                        <i class="voyager-plus"></i> Ajouter
                                    </button>
                                </form>
                            @endunless

                            @if ($sponsors->isEmpty())
                                <p class="prep-empty">
                                    Aucun sponsor. Les publicités des entreprises ajoutées ici
                                    seront mises en avant sur les contenus issus de cette édition.
                                </p>
                            @else
                                <div class="prep-list">
                                    @foreach ($sponsors as $sponsor)
                                        <div class="prep-row">
                                            <span class="prep-name">{{ $sponsor->name }}</span>
                                            <span class="prep-spacer"></span>
                                            @unless ($locked)
                                                <button type="button" class="btn btn-sm btn-danger prep-remove"
                                                    data-url="{{ route('dashboard.events.prepare.sponsors.remove', [$edition->id, $sponsor->id]) }}"
                                                    data-label="{{ $sponsor->name }}">
                                                    <i class="voyager-trash"></i>
                                                </button>
                                            @endunless
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
        <div class="prep-pane" data-pane="participants" style="display:none">
            <div class="row">
                <div class="col-md-8">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Personnes présentes</h3>
                        </div>
                        <div class="panel-body">
                            @unless ($locked)
                                <form class="prep-add"
                                    action="{{ route('dashboard.events.prepare.participants.add', $edition->id) }}"
                                    method="POST">
                                    @csrf
                                    <select class="form-control" name="user_id" id="participant-select" required></select>
                                    <button type="submit" class="btn btn-success">
                                        <i class="voyager-plus"></i> Ajouter
                                    </button>
                                </form>
                            @endunless

                            @if ($participants->isEmpty())
                                <p class="prep-empty">
                                    Aucun participant. Les publicités consultées par ces personnes
                                    ne seront pas facturées aux sponsors.
                                </p>
                            @else
                                <div class="prep-list">
                                    @foreach ($participants as $participant)
                                        <div class="prep-row">
                                            <span class="prep-name">
                                                {{ trim(($participant->first_name ?? '') . ' ' . ($participant->name ?? '')) ?: '—' }}
                                            </span>
                                            <span class="prep-mail">{{ $participant->email }}</span>
                                            <span class="prep-spacer"></span>
                                            @unless ($locked)
                                                <button type="button" class="btn btn-sm btn-danger prep-remove"
                                                    data-url="{{ route('dashboard.events.prepare.participants.remove', [$edition->id, $participant->id]) }}"
                                                    data-label="{{ $participant->email }}">
                                                    <i class="voyager-trash"></i>
                                                </button>
                                            @endunless
                                        </div>
                                    @endforeach
                                </div>

                                <div class="prep-pagination">
                                    {{ $participants->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------- Paramètres ---------- --}}
        <div class="prep-pane" data-pane="settings" style="display:none">
            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Avantage consenti aux sponsors</h3>
                        </div>
                        <div class="panel-body">
                            <form action="{{ route('dashboard.events.prepare.settings', $edition->id) }}" method="POST">
                                @csrf

                                <div class="form-group">
                                    <label for="ads_multiplier">Coefficient d'affichage</label>
                                    <input type="number" class="form-control" id="ads_multiplier" name="ads_multiplier"
                                        min="1" max="10" step="0.5"
                                        value="{{ old('ads_multiplier', $settings->ads_multiplier) }}"
                                        @disabled($locked) required>
                                    <p class="help-block">
                                        Multiplie les chances qu'une publicité de sponsor apparaisse
                                        sur un contenu issu de cette édition.
                                    </p>
                                </div>

                                <div class="form-group">
                                    <label for="ads_months">Durée de l'avantage</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="ads_months" name="ads_months"
                                            min="1" max="60" step="1"
                                            value="{{ old('ads_months', $settings->ads_months) }}"
                                            @disabled($locked) required>
                                        <span class="input-group-addon">mois</span>
                                    </div>
                                    <p class="help-block">
                                        Comptée à partir de la date de publication de chaque contenu.
                                    </p>
                                </div>

                                @unless ($locked)
                                    <button type="submit" class="btn btn-primary">
                                        <i class="voyager-check"></i> Enregistrer
                                    </button>

                                    @unless ($settings->saved)
                                        <span class="prep-defaults">Valeurs par défaut, non encore enregistrées.</span>
                                    @endunless
                                @endunless
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Retrait : formulaire distinct, hors des formulaires d'ajout --}}
        <form action="" method="POST" id="remove-form" style="display:none">
            @csrf
            @method('DELETE')
        </form>

        {{-- Clôture de l'édition --}}
        @unless ($locked)
            <form action="{{ route('dashboard.events.prepare.complete', $edition->id) }}" method="POST" id="complete-form"
                style="display:none">
                @csrf
            </form>
        @endunless
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
    <style>
        /* --- Statut dans le titre --- */
        .prep-status {
            display: inline-block;
            margin-left: 10px;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 400;
            letter-spacing: .02em;
            vertical-align: middle;
        }

        .prep-status.is-done {
            background: #eef1f5;
            color: #8a9199;
        }

        .prep-status.is-coming {
            background: #e8f7ee;
            color: #27ae60;
        }

        /* --- Onglets --- */
        .prep-tabs {
            display: inline-flex;
            gap: 4px;
            margin-bottom: 18px;
            padding: 4px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
        }

        .prep-tab {
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

        .prep-tab:hover {
            color: #1d3557;
            background: #f4f7fb;
        }

        .prep-tab.is-active,
        .prep-tab.is-active:hover {
            background: #1d3557;
            color: #fff;
            box-shadow: 0 2px 6px rgba(29, 53, 87, .28);
        }

        .prep-tab-count {
            padding: 1px 8px;
            border-radius: 10px;
            background: rgba(0, 0, 0, .07);
            font-size: 11px;
            font-weight: 600;
        }

        .prep-tab.is-active .prep-tab-count {
            background: rgba(255, 255, 255, .22);
        }

        /* --- Ajout --- */
        .prep-add {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .prep-add .select2-container {
            flex: 1;
        }

        .prep-add .btn {
            flex: 0 0 auto;
            margin-top: auto;
        }

        /* --- Listes --- */
        .prep-empty {
            margin: 0;
            padding: 18px 0 4px;
            font-size: 13px;
            color: #8a9199;
            line-height: 1.5;
        }

        .prep-list {
            border: 1px solid #e6eaf0;
            border-radius: 8px;
            overflow: hidden;
        }

        .prep-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
        }

        .prep-row+.prep-row {
            border-top: 1px solid #f2f4f7;
        }

        .prep-row:hover {
            background: #fafbfc;
        }

        .prep-name {
            font-size: 14px;
            font-weight: 600;
            color: #37474f;
        }

        .prep-mail {
            font-size: 12px;
            color: #8a9199;
        }

        .prep-spacer {
            flex: 1;
        }

        .prep-pagination {
            margin-top: 14px;
        }

        .prep-defaults {
            margin-left: 12px;
            font-size: 12px;
            color: #8a9199;
        }

        /* --- Pagination --- */
        .prep-pagination nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .prep-pagination p,
        .prep-pagination .hidden {
            margin: 0;
            font-size: 12px;
            color: #8a9199;
        }

        .prep-pagination a,
        .prep-pagination span {
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

        .prep-pagination a:hover {
            background: #f4f7fb;
            border-color: #c6ccd4;
            color: #1d3557;
        }

        /* Bouton inactif en début ou fin de liste */
        .prep-pagination span[aria-disabled="true"],
        .prep-pagination .cursor-default {
            color: #c6ccd4;
            background: #fafbfc;
        }

        /* Page courante */
        .prep-pagination span[aria-current="page"] span {
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
                var $tab = $('.prep-tab[data-tab="' + name + '"]');

                if (!$tab.length) return;

                $('.prep-tab').removeClass('is-active');
                $tab.addClass('is-active');

                $('.prep-pane').each(function() {
                    $(this).toggle($(this).data('pane') === name);
                });
            }

            $('.prep-tab').on('click', function() {
                var name = $(this).data('tab');

                // L'onglet courant survit au rechargement qui suit un envoi
                history.replaceState(null, '', '#' + name);
                showTab(name);
            });

            if (window.location.hash) {
                showTab(window.location.hash.slice(1));
            }

            /* ---------- Sponsors ---------- */
            if ($('#sponsor-select').length) {
                $('#sponsor-select').select2({
                    width: '100%',
                    placeholder: 'Rechercher une entreprise',
                    allowClear: true,
                    language: {
                        noResults: function() {
                            return 'Aucun résultat';
                        },
                        searching: function() {
                            return 'Recherche…';
                        }
                    },
                    ajax: {
                        url: '{{ route('dashboard.events.prepare.search-companies', $edition->id) }}',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                q: params.term
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    }
                });
            }

            /* ---------- Participants ---------- */
            if ($('#participant-select').length) {
                $('#participant-select').select2({
                    width: '100%',
                    placeholder: 'Rechercher par nom ou adresse e-mail',
                    allowClear: true,
                    minimumInputLength: 3,
                    language: {
                        inputTooShort: function() {
                            return 'Saisissez au moins 3 caractères';
                        },
                        noResults: function() {
                            return 'Aucun résultat';
                        },
                        searching: function() {
                            return 'Recherche…';
                        }
                    },
                    ajax: {
                        url: '{{ route('dashboard.events.prepare.search-users', $edition->id) }}',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                q: params.term
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    }
                });
            }

            /* ---------- Retrait ---------- */
            $('.prep-remove').on('click', function() {
                if (!confirm('Retirer ' + $(this).data('label') + ' ?')) {
                    return;
                }

                $('#remove-form').attr('action', $(this).data('url')).submit();
            });

            /* ---------- Clôture ---------- */
            $('#btn-complete').on('click', function() {
                // La clôture fige la préparation : elle mérite une confirmation
                if (!confirm('Terminer cette édition ? La préparation ne sera plus modifiable.')) {
                    return;
                }

                $('#complete-form').submit();
            });
        });
    </script>
@stop
