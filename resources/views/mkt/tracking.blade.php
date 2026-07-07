@extends('voyager::master')

@section('page_title', 'Tracking des liens')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-pie-chart"></i> Tracking des liens
        </h1>
        <a href="{{ route('dashboard.short-links') }}" class="btn btn-default btn-add-new">
            <i class="voyager-list"></i> <span>Retour à la liste</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content">
        <div class="analytics-container">

            {{-- ================= FILTRES ================= --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="voyager-settings"></i> Filtres</h3>
                        </div>
                        <div class="panel-body">
                            <div class="trk-filters">
                                <div class="trk-filter">
                                    <label>Short link</label>
                                    <select id="filterLink" class="form-control input-sm">
                                        <option value="">Tous les liens</option>
                                        @foreach ($links as $l)
                                            <option value="{{ $l->id }}" {{ (int) $shortLinkId === (int) $l->id ? 'selected' : '' }}>
                                                /sh/{{ $l->code }}{{ $l->title ? ' — ' . $l->title : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="trk-filter">
                                    <label>Source</label>
                                    <select id="filterSource" class="form-control input-sm">
                                        <option value="">Toutes</option>
                                        @foreach ($sources as $s)
                                            <option value="{{ $s }}" {{ $source === $s ? 'selected' : '' }}>{{ $s }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="trk-filter">
                                    <label>Campagne</label>
                                    <select id="filterCampaign" class="form-control input-sm">
                                        <option value="">Toutes</option>
                                        @foreach ($campaigns as $c)
                                            <option value="{{ $c }}" {{ $campaign === $c ? 'selected' : '' }}>{{ $c }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="trk-filter">
                                    <label>Type</label>
                                    <select id="filterType" class="form-control input-sm">
                                        <option value="">Tous</option>
                                        <option value="shortlink" {{ $type === 'shortlink' ? 'selected' : '' }}>Short link</option>
                                        <option value="qrcode" {{ $type === 'qrcode' ? 'selected' : '' }}>QR code</option>
                                    </select>
                                </div>

                                <div class="trk-filter">
                                    <label>Granularité</label>
                                    <select id="filterGranularity" class="form-control input-sm">
                                        <option value="day" {{ $granularity === 'day' ? 'selected' : '' }}>Par jour</option>
                                        <option value="week" {{ $granularity === 'week' ? 'selected' : '' }}>Par semaine</option>
                                        <option value="month" {{ $granularity === 'month' ? 'selected' : '' }}>Par mois</option>
                                    </select>
                                </div>

                                <div class="trk-filter">
                                    <label>Du</label>
                                    <input type="date" id="filterFrom" class="form-control input-sm" value="{{ $from }}">
                                </div>
                                <div class="trk-filter">
                                    <label>Au</label>
                                    <input type="date" id="filterTo" class="form-control input-sm" value="{{ $to }}">
                                </div>

                                <div class="trk-filter trk-filter-actions">
                                    <button type="button" id="applyFilters" class="btn btn-sm btn-info">Appliquer</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ================= RÉSUMÉ (KPI) ================= --}}
            <div class="row" id="kpiRow">
                <div class="col-md-3 col-sm-6">
                    <div class="trk-kpi">
                        <div class="trk-kpi-label">Total clics</div>
                        <div class="trk-kpi-value" id="kpiClicks">{{ number_format($summary['total_clicks'], 0, ',', ' ') }}</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="trk-kpi">
                        <div class="trk-kpi-label">Visiteurs uniques</div>
                        <div class="trk-kpi-value" id="kpiVisitors">{{ number_format($summary['unique_visitors'], 0, ',', ' ') }}</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="trk-kpi">
                        <div class="trk-kpi-label">Liens actifs</div>
                        <div class="trk-kpi-value" id="kpiActive">{{ number_format($summary['active_links'], 0, ',', ' ') }}</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="trk-kpi">
                        <div class="trk-kpi-label">Part mobile</div>
                        <div class="trk-kpi-value" id="kpiMobile">{{ $summary['mobile_share'] }} %</div>
                    </div>
                </div>
            </div>

            {{-- ================= COURBE PRINCIPALE ================= --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Clics dans le temps</h3>
                        </div>
                        <div class="panel-body">
                            <div style="min-height:420px;max-height:520px">
                                <canvas id="trkTimeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ================= RÉPARTITIONS ================= --}}
            <div class="row">
                <div class="col-md-4 col-sm-6">
                    <div class="panel panel-bordered">
                        <div class="panel-heading"><h3 class="panel-title">Par type d'appareil</h3></div>
                        <div class="panel-body"><div style="min-height:280px"><canvas id="trkDeviceChart"></canvas></div></div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="panel panel-bordered">
                        <div class="panel-heading"><h3 class="panel-title">Par navigateur</h3></div>
                        <div class="panel-body"><div style="min-height:280px"><canvas id="trkBrowserChart"></canvas></div></div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="panel panel-bordered">
                        <div class="panel-heading"><h3 class="panel-title">Par plateforme</h3></div>
                        <div class="panel-body"><div style="min-height:280px"><canvas id="trkPlatformChart"></canvas></div></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-bordered">
                        <div class="panel-heading"><h3 class="panel-title">Par pays</h3></div>
                        <div class="panel-body"><div style="min-height:320px"><canvas id="trkCountryChart"></canvas></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .trk-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
        .trk-filter{display:flex;flex-direction:column;gap:4px}
        .trk-filter label{font-size:11px;font-weight:600;color:#5c6b7e;text-transform:uppercase;letter-spacing:.4px;margin:0}
        .trk-filter select,.trk-filter input{min-width:150px}
        .trk-filter-actions{justify-content:flex-end}

        .trk-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;margin-bottom:20px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
        .trk-kpi-label{font-size:12px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px}
        .trk-kpi-value{font-size:28px;font-weight:700;color:#1d3557;margin-top:6px;line-height:1}
    </style>
@stop

@section('javascript')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const TRK_CONFIG = {
            dataUrl: @json($chartDataUrl),
        };

        const PIE_COLORS = [
            '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
            '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16',
            '#06B6D4', '#A855F7'
        ];

        let timeChart, deviceChart, browserChart, platformChart, countryChart;

        // ---------- Courbe temporelle ----------
        function renderTimeChart(payload) {
            const ctx = document.getElementById('trkTimeChart');
            if (timeChart) timeChart.destroy();
            timeChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: payload.labels,
                    datasets: [
                        {
                            label: 'Clics',
                            data: payload.clicks,
                            borderColor: 'rgba(59,130,246,1)',
                            backgroundColor: 'rgba(59,130,246,.1)',
                            fill: true, borderWidth: 2, tension: .3, pointRadius: 3,
                        },
                        {
                            label: 'Visiteurs uniques',
                            data: payload.visitors,
                            borderColor: 'rgba(16,185,129,1)',
                            backgroundColor: 'rgba(16,185,129,.1)',
                            fill: true, borderWidth: 2, tension: .3, pointRadius: 3,
                        }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { ticks: { maxRotation: 45, minRotation: 0 } },
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        // ---------- Camembert générique ----------
        function renderPie(instanceRef, canvasId, data) {
            const ctx = document.getElementById(canvasId);
            if (instanceRef) instanceRef.destroy();
            return new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: PIE_COLORS,
                        borderWidth: 1, borderColor: '#fff',
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
                }
            });
        }

        // ---------- Barres pays ----------
        function renderCountry(payload) {
            const ctx = document.getElementById('trkCountryChart');
            if (countryChart) countryChart.destroy();
            countryChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: payload.labels,
                    datasets: [{
                        label: 'Clics',
                        data: payload.values,
                        backgroundColor: 'rgba(59,130,246,.8)',
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }

        function renderAll(data) {
            renderTimeChart(data.chart);
            deviceChart   = renderPie(deviceChart, 'trkDeviceChart', data.breakdowns.device);
            browserChart  = renderPie(browserChart, 'trkBrowserChart', data.breakdowns.browser);
            platformChart = renderPie(platformChart, 'trkPlatformChart', data.breakdowns.platform);
            renderCountry(data.breakdowns.country);

            // KPI
            document.getElementById('kpiClicks').textContent   = (data.summary.total_clicks).toLocaleString('fr-FR');
            document.getElementById('kpiVisitors').textContent = (data.summary.unique_visitors).toLocaleString('fr-FR');
            document.getElementById('kpiActive').textContent   = (data.summary.active_links).toLocaleString('fr-FR');
            document.getElementById('kpiMobile').textContent   = data.summary.mobile_share + ' %';
        }

        // Rendu initial avec données server-side
        renderAll({
            chart: @json($chart),
            summary: @json($summary),
            breakdowns: @json($breakdowns),
        });

        // ---------- Filtres ----------
        function buildParams() {
            const params = new URLSearchParams();
            params.set('from', document.getElementById('filterFrom').value);
            params.set('to', document.getElementById('filterTo').value);
            params.set('granularity', document.getElementById('filterGranularity').value);

            const link = document.getElementById('filterLink').value;
            const src  = document.getElementById('filterSource').value;
            const camp = document.getElementById('filterCampaign').value;
            const type = document.getElementById('filterType').value;

            if (link) params.set('short_link_id', link);
            if (src)  params.set('utm_source', src);
            if (camp) params.set('utm_campaign', camp);
            if (type) params.set('source_type', type);
            return params;
        }

        document.getElementById('applyFilters').addEventListener('click', function () {
            const url = TRK_CONFIG.dataUrl + '?' + buildParams().toString();
            fetch(url)
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(data => renderAll(data))
                .catch(err => { console.error(err); alert('Erreur lors du chargement des données'); });
        });
    </script>
@stop