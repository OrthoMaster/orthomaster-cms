@extends('voyager::master')

@section('page_title', ' Médias')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-play"></i> Médias
        </h1>
        <a href="{{ route('medias.create') }}" class="btn btn-success">
            <i class="voyager-plus"></i> <span>Ajouter un média</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            <div class="col-lg-12">

                {{-- ============ FILTRES ============ --}}
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="voyager-search"></i> Filtres</h3>
                    </div>
                    <div class="panel-body">
                        <form class="row" action="{{ route('medias.index') }}" method="GET">
                            <div class="col-md-3">
                                <label for="expert_id">Auteur</label>
                                <select class="form-control" name="expert_id" id="expert_id">
                                    <option value=""></option>
                                    @if ($filters['expert_id'])
                                        <option value="{{ $filters['expert_id'] }}" selected>{{ $selectedExpert }}</option>
                                    @endif
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="content_id">Vidéo</label>
                                <select class="form-control" name="content_id" id="content_id">
                                    <option value=""></option>
                                    @if ($filters['content_id'])
                                        <option value="{{ $filters['content_id'] }}" selected>{{ $selectedVideo }}</option>
                                    @endif
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="granularity">Granularité</label>
                                <select class="form-control" name="granularity" id="granularity">
                                    <option value="day" @selected($filters['granularity'] === 'day')>Jour</option>
                                    <option value="month" @selected($filters['granularity'] === 'month')>Mois</option>
                                    <option value="year" @selected($filters['granularity'] === 'year')>Année</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="from">Du</label>
                                <input type="date" class="form-control" name="from" id="from"
                                    value="{{ $filters['from'] }}">
                            </div>

                            <div class="col-md-2">
                                <label for="to">Au</label>
                                <input type="date" class="form-control" name="to" id="to"
                                    value="{{ $filters['to'] }}">
                            </div>

                            <div class="col-md-12" style="margin-top: 15px; margin-bottom: 0">
                                <button type="submit" class="btn btn-primary pull-right">
                                    Appliquer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ============ KPI ============ --}}
                <div class="row row-eq-height">
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="panel panel-bordered kpi-panel kpi-blue">
                            <div class="panel-body">
                                <i class="voyager-play kpi-icon"></i>
                                <div class="kpi-label">Vidéos</div>
                                <div class="kpi-value">{{ number_format($summary['total_videos'], 0, ',', ' ') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="panel panel-bordered kpi-panel kpi-purple">
                            <div class="panel-body">
                                <i class="voyager-eye kpi-icon"></i>
                                <div class="kpi-label">Vues totales (historique)</div>
                                <div class="kpi-value">{{ number_format($summary['total_views_all'], 0, ',', ' ') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="panel panel-bordered kpi-panel kpi-green">
                            <div class="panel-body">
                                <i class="voyager-line-chart kpi-icon"></i>
                                <div class="kpi-label">Vues sur la période</div>
                                <div class="kpi-value">{{ number_format($summary['total_views_period'], 0, ',', ' ') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="panel panel-bordered kpi-panel kpi-red">
                            <div class="panel-body">
                                <i class="voyager-people kpi-icon"></i>
                                <div class="kpi-label">Spectateurs uniques</div>
                                <div class="kpi-value">{{ number_format($summary['total_viewers'], 0, ',', ' ') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ CHART 1 ============ --}}
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="voyager-line-chart"></i> Vues sur la période</h3>
                    </div>
                    <div class="panel-body">
                        <div style="height:300px"><canvas id="chartTimeline"></canvas></div>
                    </div>
                </div>

                {{-- ============ CHART 2 + 3 ============ --}}
                <div class="row row-eq-height">
                    <div class="col-md-6">
                        <div class="panel panel-bordered">
                            <div class="panel-heading">
                                <h3 class="panel-title"><i class="voyager-bar-chart"></i> Répartition par heure (24h)</h3>
                            </div>
                            <div class="panel-body">
                                <div style="height:320px"><canvas id="chartHours"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="panel panel-bordered">
                            <div class="panel-heading">
                                <h3 class="panel-title"><i class="voyager-people"></i> Vues par profession</h3>
                            </div>
                            <div class="panel-body">
                                <div style="height:320px"><canvas id="chartProfessions"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ TABLE ============ --}}
                @if (!$medias->count() > 0)
                    <div class="alert alert-warning">
                        <strong>Aucune information n'est disponible !</strong>
                        <p>Vous pouvez modifier les filtres pour trouver les bonnes informations.</p>
                    </div>
                @else
                    <div class="panel panel-bordered">
                        <div class="panel-body">
                            <div class="table-responsive">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div>Trouvez <b>{{ $medias->total() }}</b>
                                            {{ $medias->total() > 1 ? 'résultats' : 'résultat' }}
                                        </div>
                                        <span>Page <b>{{ $medias->currentPage() }}/{{ $medias->lastPage() }}</b></span>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="table-navigation">
                                            {{ $medias->links() }}
                                        </div>
                                    </div>
                                </div>
                                <table class="table table-striped database-tables">
                                    <thead>
                                        <tr>
                                            <th>Cont ID</th>
                                            <th>Titre</th>
                                            <th>Auteur</th>
                                            <th>Vues (période)</th>
                                            <th>Vues (total)</th>
                                            <th class="actions text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($medias as $media)
                                            <tr>
                                                <td>{{ $media->id_content }}</td>
                                                <td><b>{{ $media->title }}</b></td>
                                                <td>{{ $media->author ?: '—' }}</td>
                                                <td>{{ number_format($media->period_views, 0, ',', ' ') }}</td>
                                                <td>{{ number_format($media->views, 0, ',', ' ') }}</td>

                                                <td class="no-sort no-click text-right">
                                                    @if ($media->video_id && $media->version === 'v2')
                                                        <a href="{{ route('medias.edit', $media->id_content) }}"
                                                           class="btn btn-sm btn-primary edit">
                                                            <i class="voyager-edit"></i> <span class="hidden-xs hidden-sm">Modifier</span>
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="table-navigation" style="margin-bottom: 2em">
                                    {{ $medias->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
    <style>
        /* --- Colonnes de même hauteur (KPI + charts) --- */
        .row-eq-height {
            display: flex;
            flex-wrap: wrap;
        }

        .row-eq-height > [class*='col-'] {
            display: flex;
        }

        .row-eq-height .panel {
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        .row-eq-height .panel-body {
            flex: 1 1 auto;
        }

        @media (max-width: 991px) {
            .row-eq-height {
                display: block;
            }

            .row-eq-height > [class*='col-'] {
                display: block;
            }
        }

        /* --- Cartes KPI --- */
        .kpi-panel {
            position: relative;
            overflow: hidden;
            border-left: 4px solid #ddd;
        }

        .kpi-panel .panel-body {
            padding: 18px 20px;
        }

        .kpi-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #8a9199;
            margin-bottom: 6px;
            padding-right: 40px;
        }

        .kpi-value {
            font-size: 30px;
            font-weight: 700;
            line-height: 1.1;
        }

        .kpi-icon {
            position: absolute;
            top: 16px;
            right: 16px;
            font-size: 34px;
            opacity: .16;
        }

        .kpi-blue   { border-left-color: #22A7F0; }
        .kpi-purple { border-left-color: #955BA5; }
        .kpi-green  { border-left-color: #2ECC71; }
        .kpi-red    { border-left-color: #E74C3C; }

        .kpi-blue   .kpi-value, .kpi-blue   .kpi-icon { color: #22A7F0; }
        .kpi-purple .kpi-value, .kpi-purple .kpi-icon { color: #955BA5; }
        .kpi-green  .kpi-value, .kpi-green  .kpi-icon { color: #2ECC71; }
        .kpi-red    .kpi-value, .kpi-red    .kpi-icon { color: #E74C3C; }
    </style>
@stop

@section('javascript')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        var CHART = @json($chart);

        $(document).ready(function () {

            /* ---------- Select2 auteur ---------- */
            $('#expert_id').select2({
                width: '100%',
                placeholder: 'Tous les auteurs',
                allowClear: true,
                minimumInputLength: 3,
                language: {
                    inputTooShort: function () { return 'Saisissez au moins 3 caractères'; },
                    noResults: function () { return 'Aucun résultat'; },
                    searching: function () { return 'Recherche…'; }
                },
                ajax: {
                    url: '{{ route('medias.search-experts') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term }; },
                    processResults: function (data) { return { results: data }; },
                    cache: true
                }
            });

            /* ---------- Select2 vidéo (dépend de l'auteur) ---------- */
            function videoPlaceholder() {
                var d = $('#expert_id').select2('data');
                return (d.length && d[0].id) ? 'Toutes les vidéos de ' + d[0].text : 'Toutes les vidéos';
            }

            function initVideoSelect() {
                if ($('#content_id').hasClass('select2-hidden-accessible')) {
                    $('#content_id').select2('destroy');
                }

                $('#content_id').select2({
                    width: '100%',
                    placeholder: videoPlaceholder(),
                    allowClear: true,
                    minimumInputLength: 3,
                    language: {
                        inputTooShort: function () { return 'Saisissez au moins 3 caractères'; },
                        noResults: function () { return 'Aucun résultat'; },
                        searching: function () { return 'Recherche…'; }
                    },
                    ajax: {
                        url: '{{ route('medias.search-videos') }}',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return { q: params.term, expert_id: $('#expert_id').val() };
                        },
                        processResults: function (data) { return { results: data }; },
                        cache: true
                    }
                });
            }

            initVideoSelect();

            // Changement d'auteur -> reset vidéo + nouveau placeholder
            $('#expert_id').on('change', function () {
                $('#content_id').val(null).find('option[value!=""]').remove();
                initVideoSelect();
            });

            /* ---------- Chart 1 : timeline ---------- */
            new Chart(document.getElementById('chartTimeline'), {
                type: 'line',
                data: {
                    labels: CHART.timeline.labels,
                    datasets: [{
                        label: 'Vues',
                        data: CHART.timeline.views,
                        borderColor: '#22A7F0',
                        backgroundColor: 'rgba(34,167,240,.15)',
                        fill: true,
                        tension: .3,
                        pointRadius: 3
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });

            /* ---------- Chart 2 : par heure ---------- */
            new Chart(document.getElementById('chartHours'), {
                type: 'bar',
                data: {
                    labels: CHART.hours.labels,
                    datasets: [{ label: 'Vues', data: CHART.hours.views, backgroundColor: '#E74C3C' }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });

            /* ---------- Chart 3 : par profession ---------- */
            new Chart(document.getElementById('chartProfessions'), {
                type: 'bar',
                data: {
                    labels: CHART.professions.labels,
                    datasets: [
                        { label: 'Vues',        data: CHART.professions.views,   backgroundColor: '#2ECC71' },
                        { label: 'Spectateurs', data: CHART.professions.viewers, backgroundColor: '#E74C3C' }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        x: { ticks: { autoSkip: false, maxRotation: 45, minRotation: 0 } },
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        });
    </script>
@stop