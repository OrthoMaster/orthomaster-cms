@extends('voyager::master')

@section('page_title', ' Utilisateurs')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-people"></i> Utilisateurs
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            <div class="col-lg-12">

                {{-- ============ KPI GLOBAUX ============ --}}
                <div class="row row-eq-height">
                    <div class="col-lg-4 col-md-4 col-sm-12">
                        <div class="panel panel-bordered kpi-panel kpi-blue">
                            <div class="panel-body">
                                <i class="voyager-person kpi-icon"></i>
                                <div class="kpi-label">Utilisateurs actifs</div>
                                <div class="kpi-value">{{ number_format($global['active_users'], 0, ',', ' ') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-4 col-sm-12">
                        <div class="panel panel-bordered kpi-panel kpi-purple">
                            <div class="panel-body">
                                <i class="voyager-group kpi-icon"></i>
                                <div class="kpi-label">Utilisateurs cumulés</div>
                                <div class="kpi-value">{{ number_format($global['total_users'], 0, ',', ' ') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-4 col-sm-12">
                        <div class="panel panel-bordered kpi-panel kpi-green">
                            <div class="panel-body">
                                <i class="voyager-company kpi-icon"></i>
                                <div class="kpi-label">Sociétés actives</div>
                                <div class="kpi-value">{{ number_format($global['companies'], 0, ',', ' ') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ CHART 1 + 2 ============ --}}
                <div class="row row-eq-height">
                    <div class="col-md-8">
                        <div class="panel panel-bordered">
                            <div class="panel-heading">
                                <h3 class="panel-title"><i class="voyager-bar-chart"></i> Utilisateurs par profession</h3>
                            </div>
                            <div class="panel-body">
                                <div style="height:360px"><canvas id="chartProfessions"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="panel panel-bordered">
                            <div class="panel-heading">
                                <h3 class="panel-title"><i class="voyager-phone"></i> Accès par appareil</h3>
                            </div>
                            <div class="panel-body">
                                <div style="height:360px"><canvas id="chartDevicesGlobal"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ PÉRIODE ============ --}}
                <div class="panel panel-bordered panel-period" id="periode">
                    <div class="panel-heading">
                        <h3 class="panel-title" style="display:inline-block; margin-right:24px">
                            <i class="voyager-activity"></i> Connexions et nouvelles inscriptions
                        </h3>

                        <form class="row filters-inline" id="period-filters"
                              action="{{ route('dashboard.users.index') }}" method="GET">
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

                            <div class="col-md-3" style="padding-top:18px">
                                <button type="submit" class="btn btn-primary">Appliquer</button>
                                <button type="button" id="btn-export" class="btn btn-success">
                                    <i class="voyager-file-text"></i> Excel
                                </button>
                            </div>

                            <div class="col-md-3" style="padding-top:14px">
                                <div class="panel-stats pull-right">
                                    <div class="panel-stat is-opens">
                                        <span>Connexions</span>
                                        <strong id="stat-connections">{{ number_format($periodKp['connections'], 0, ',', ' ') }}</strong>
                                    </div>
                                    <div class="panel-stat is-signups">
                                        <span>Nouveaux inscrits</span>
                                        <strong id="stat-signups">{{ number_format($periodKp['new_users'], 0, ',', ' ') }}</strong>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="panel-body">

                        {{-- ---- Chart 3 ---- --}}
                        <div style="height:320px; margin-bottom:24px">
                            <canvas id="chartActivity"></canvas>
                        </div>

                        {{-- ---- Charts 4, 5, 6 ---- --}}
                        <div class="row row-eq-height">
                            <div class="col-md-3">
                                <div class="panel panel-bordered">
                                    <div class="panel-heading">
                                        <h3 class="panel-title">Appareils (période)</h3>
                                    </div>
                                    <div class="panel-body">
                                        <div style="height:280px"><canvas id="chartDevicesPeriod"></canvas></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="panel panel-bordered">
                                    <div class="panel-heading">
                                        <h3 class="panel-title">Par heure (24h)</h3>
                                    </div>
                                    <div class="panel-body">
                                        <div style="height:280px"><canvas id="chartHours"></canvas></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="panel panel-bordered">
                                    <div class="panel-heading">
                                        <h3 class="panel-title">Par jour de la semaine</h3>
                                    </div>
                                    <div class="panel-body">
                                        <div style="height:280px"><canvas id="chartWeekdays"></canvas></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
    @include('dashboard.users._styles')
@stop

@section('javascript')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        var CHART  = @json($chart);
        var GLOBAL = @json($global);
        var DEVICE_COLORS = ['#22A7F0', '#2ECC71', '#F39C12'];

        $(document).ready(function () {

            $('#granularity').select2({ width: '100%' });

            /* ---------- Formatage FR des nombres ---------- */
            function fr(n) {
                return Number(n).toLocaleString('fr-FR').replace(/\u202F|\u00A0|,/g, ' ');
            }

            /* ---------- Camemberts (charts 2 et 4) ---------- */
            function pieConfig(series) {
                return {
                    type: 'doughnut',
                    data: {
                        labels: series.labels,
                        datasets: [{ data: series.values, backgroundColor: DEVICE_COLORS, borderWidth: 0 }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0),
                                            pct   = total ? Math.round(ctx.parsed / total * 1000) / 10 : 0;
                                        return ctx.label + ' : ' + fr(ctx.parsed) + ' (' + pct + ' %)';
                                    }
                                }
                            }
                        }
                    }
                };
            }

            /* ---------- Charts globaux (hors filtre) ---------- */
            new Chart(document.getElementById('chartProfessions'), {
                type: 'bar',
                data: {
                    labels: GLOBAL.professions.labels,
                    datasets: [{
                        label: 'Utilisateurs',
                        data: GLOBAL.professions.values,
                        backgroundColor: '#22A7F0'
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { autoSkip: false, maxRotation: 45, minRotation: 0 } },
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });

            new Chart(document.getElementById('chartDevicesGlobal'), pieConfig(GLOBAL.devices));

            /* ---------- Charts de la période, redessinés sans rechargement ---------- */
            var periodCharts = {};

            function buildPeriodCharts(data) {
                Object.keys(periodCharts).forEach(function (k) { periodCharts[k].destroy(); });

                periodCharts.activity = new Chart(document.getElementById('chartActivity'), {
                    type: 'line',
                    data: {
                        labels: data.activity.labels,
                        datasets: [
                            {
                                label: 'Ouvertures de l\'application',
                                data: data.activity.opens,
                                borderColor: '#22A7F0',
                                backgroundColor: 'rgba(34,167,240,.15)',
                                fill: true,
                                tension: .3,
                                pointRadius: 0,
                                pointHoverRadius: 4,
                                yAxisID: 'yOpens'
                            },
                            {
                                label: 'Nouveaux inscrits',
                                data: data.activity.signups,
                                borderColor: '#F39C12',
                                backgroundColor: 'rgba(243,156,18,.15)',
                                fill: true,
                                tension: .3,
                                pointRadius: 0,
                                pointHoverRadius: 4,
                                yAxisID: 'ySignups'
                            }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { position: 'bottom' } },
                        scales: {
                            yOpens: {
                                type: 'linear',
                                position: 'left',
                                beginAtZero: true,
                                ticks: { precision: 0, color: '#22A7F0' },
                                title: { display: true, text: 'Ouvertures', color: '#22A7F0' }
                            },
                            ySignups: {
                                type: 'linear',
                                position: 'right',
                                beginAtZero: true,
                                ticks: { precision: 0, color: '#F39C12' },
                                title: { display: true, text: 'Inscriptions', color: '#F39C12' },
                                grid: { drawOnChartArea: false }
                            }
                        }
                    }
                });

                periodCharts.devices = new Chart(
                    document.getElementById('chartDevicesPeriod'),
                    pieConfig(data.devices)
                );

                periodCharts.hours = new Chart(document.getElementById('chartHours'), {
                    type: 'bar',
                    data: {
                        labels: data.hours.labels,
                        datasets: [{ label: 'Connexions', data: data.hours.values, backgroundColor: '#E74C3C' }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });

                periodCharts.weekdays = new Chart(document.getElementById('chartWeekdays'), {
                    type: 'bar',
                    data: {
                        labels: data.weekdays.labels,
                        datasets: [{ label: 'Connexions', data: data.weekdays.values, backgroundColor: '#955BA5' }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { maxRotation: 45, minRotation: 0 } },
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                });
            }

            buildPeriodCharts(CHART);

            /* ---------- Soumission sans rechargement ---------- */
            var $form = $('#period-filters'),
                $btn  = $form.find('button[type=submit]');

            $form.on('submit', function (e) {
                e.preventDefault();

                var params = $form.serialize();

                $btn.prop('disabled', true).text('Chargement…');

                $.getJSON('{{ route('dashboard.users.data') }}', params)
                    .done(function (data) {
                        buildPeriodCharts(data);

                        $('#stat-connections').text(fr(data.summary.connections));
                        $('#stat-signups').text(fr(data.summary.new_users));

                        // L'URL reflète les filtres, pour partage / rafraîchissement
                        history.replaceState(null, '', '?' + params);
                    })
                    .fail(function () {
                        alert('Impossible de charger les données. Veuillez réessayer.');
                    })
                    .always(function () {
                        $btn.prop('disabled', false).text('Appliquer');
                    });
            });

            /* ---------- Export Excel : reprend les filtres courants ---------- */
            $('#btn-export').on('click', function () {
                window.location = '{{ route('dashboard.users.export') }}?' + $form.serialize();
            });
        });
    </script>
@stop