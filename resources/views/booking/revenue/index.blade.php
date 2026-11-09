@extends('voyager::master')

@section('page_title', ' Revenus des rendez-vous')

@php
    $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €';
@endphp

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-dollar"></i> Revenus des rendez-vous
        </h1>
        <a href="{{ route('booking.appointments.index') }}" class="btn btn-default">
            <i class="voyager-calendar"></i> Rendez-vous
        </a>
        <a href="{{ route('booking.experts.index') }}" class="btn btn-default">
            <i class="voyager-people"></i> Experts
        </a>
        <a href="{{ route('booking.config.index') }}" class="btn btn-default">
            <i class="voyager-settings"></i> Configuration
        </a>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        {{-- ============ FILTRES ============ --}}
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="voyager-search"></i> Filtres</h3>
            </div>
            <div class="panel-body">
                <form class="row" action="{{ route('booking.revenue.index') }}" method="GET">
                    <div class="col-md-4">
                        <label for="expert_id">Expert</label>
                        <select class="form-control" name="expert_id" id="expert_id">
                            <option value="">Tous les experts</option>
                            @foreach ($experts as $e)
                                <option value="{{ $e->id }}" @selected($filters['expert_id'] == $e->id)>
                                    {{ trim(($e->honorific ? $e->honorific . ' ' : '') . $e->name) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="granularity">Période</label>
                        <select class="form-control" name="granularity" id="granularity">
                            <option value="day" @selected($filters['granularity'] === 'day')>Jour</option>
                            <option value="week" @selected($filters['granularity'] === 'week')>Semaine</option>
                            <option value="month" @selected($filters['granularity'] === 'month')>Mois</option>
                            <option value="year" @selected($filters['granularity'] === 'year')>Année</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="from">Du</label>
                        <input type="date" class="form-control" name="from" id="from" value="{{ $filters['from'] }}">
                    </div>
                    <div class="col-md-2">
                        <label for="to">Au</label>
                        <input type="date" class="form-control" name="to" id="to" value="{{ $filters['to'] }}">
                    </div>
                    <div class="col-md-2" style="padding-top: 25px;">
                        <button type="submit" class="btn btn-primary btn-block" style="margin: 0;">Appliquer</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ============ KPI ============ --}}
        <div class="row row-eq-height">
            <div class="col-md-4 col-sm-12">
                <div class="panel panel-bordered kpi-panel kpi-dark">
                    <div class="panel-body">
                        <i class="voyager-wallet kpi-icon"></i>
                        <div class="kpi-label">Débité (TTC)</div>
                        <div class="kpi-split">
                            <div class="kpi-part">
                                <div class="kpi-sub">Total débité sur la période</div>
                                <div class="kpi-value">{{ $eur($summary['ttc']) }}</div>
                            </div>
                            <div class="kpi-part">
                                <div class="kpi-sub">Net, remboursements déduits</div>
                                <div class="kpi-value">{{ $eur($summary['net']) }}</div>
                            </div>
                        </div>
                        <div class="kpi-foot">{{ $summary['count'] }} rendez-vous payant{{ $summary['count'] > 1 ? 's' : '' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="panel panel-bordered kpi-panel kpi-green">
                    <div class="panel-body">
                        <div class="kpi-label">Part experts (HT)</div>
                        <div class="kpi-value">{{ $eur($summary['expert']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="panel panel-bordered kpi-panel kpi-blue">
                    <div class="panel-body">
                        <div class="kpi-label">Commission (HT)</div>
                        <div class="kpi-value">{{ $eur($summary['commission']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="panel panel-bordered kpi-panel kpi-orange">
                    <div class="panel-body">
                        <div class="kpi-label">TVA</div>
                        <div class="kpi-value">{{ $eur($summary['tva']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="panel panel-bordered kpi-panel kpi-red">
                    <div class="panel-body">
                        <div class="kpi-label">Remboursé</div>
                        <div class="kpi-value">{{ $eur($summary['refunded']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ GRAPHIQUE ============ --}}
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="voyager-bar-chart"></i> Revenus sur la période</h3>
            </div>
            <div class="panel-body">
                <p class="booking-muted" style="margin-top: 0;">
                    Une barre = le total débité TTC de la période (date du débit, à l'acceptation de l'expert),
                    réparti entre la part de l'expert, la commission, la TVA et ce qui a été remboursé.
                </p>
                <div style="height: 360px"><canvas id="chartRevenue"></canvas></div>
            </div>
        </div>

        {{-- ============ PAR EXPERT ============ --}}
        @if (!$filters['expert_id'])
            <div class="panel panel-bordered">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="voyager-people"></i> Par expert</h3>
                </div>
                <div class="panel-body" style="padding-top: 0;">
                    @if (empty($byExpert))
                        <p class="booking-muted">Aucun rendez-vous payant débité sur la période.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Expert</th>
                                        <th class="text-right">Rendez-vous</th>
                                        <th class="text-right">Débité TTC</th>
                                        <th class="text-right">Remboursé</th>
                                        <th class="text-right">TVA</th>
                                        <th class="text-right">Commission HT</th>
                                        <th class="text-right">Part expert HT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($byExpert as $row)
                                        <tr>
                                            <td>
                                                <a href="{{ route('booking.revenue.index', array_merge($filters, ['expert_id' => $row['expert_id']])) }}">{{ $row['name'] }}</a>
                                            </td>
                                            <td class="text-right">{{ $row['count'] }}</td>
                                            <td class="text-right">{{ $eur($row['ttc']) }}</td>
                                            <td class="text-right">{{ $eur($row['refunded']) }}</td>
                                            <td class="text-right">{{ $eur($row['tva']) }}</td>
                                            <td class="text-right">{{ $eur($row['commission']) }}</td>
                                            <td class="text-right"><strong>{{ $eur($row['expert']) }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
@stop

@section('css')
    <style>
        .row-eq-height { display: flex; flex-wrap: wrap; }
        .row-eq-height > [class*='col-'] { display: flex; }
        .row-eq-height .panel { width: 100%; display: flex; flex-direction: column; }
        .row-eq-height .panel-body { flex: 1 1 auto; }
        @media (max-width: 991px) {
            .row-eq-height, .row-eq-height > [class*='col-'] { display: block; }
        }
        .kpi-panel { position: relative; overflow: hidden; border-left: 4px solid #ddd; }
        .kpi-panel .panel-body { padding: 18px 20px; }
        .kpi-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #8a9199; margin-bottom: 6px; padding-right: 40px; }
        .kpi-value { font-size: 22px; font-weight: 700; line-height: 1.15; }
        .kpi-icon { position: absolute; top: 16px; right: 16px; font-size: 34px; opacity: .16; }
        .kpi-split { display: flex; }
        .kpi-part { flex: 1 1 50%; }
        .kpi-part + .kpi-part { border-left: 1px solid #eceff3; padding-left: 14px; }
        .kpi-sub { font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: #a4abb3; margin-bottom: 4px; min-height: 28px; padding-right: 8px; }
        .kpi-foot { margin-top: 10px; font-size: 12px; color: #8a9199; }
        .kpi-dark { border-left-color: #34495E; } .kpi-dark .kpi-value, .kpi-dark .kpi-icon { color: #34495E; }
        .kpi-green { border-left-color: #2ECC71; } .kpi-green .kpi-value { color: #2ECC71; }
        .kpi-blue { border-left-color: #22A7F0; } .kpi-blue .kpi-value { color: #22A7F0; }
        .kpi-orange { border-left-color: #F39C12; } .kpi-orange .kpi-value { color: #F39C12; }
        .kpi-red { border-left-color: #E74C3C; } .kpi-red .kpi-value { color: #E74C3C; }
        .booking-muted { color: #8a9199; font-size: 13px; }
    </style>
@stop

@section('javascript')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        var CHART = @json($chart);
        var SERIES = @json($series);

        $(document).ready(function () {
            $('#expert_id').select2({ width: '100%' });

            var eur = function (v) {
                return Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
            };

            new Chart(document.getElementById('chartRevenue'), {
                type: 'bar',
                data: {
                    labels: CHART.labels,
                    datasets: Object.keys(SERIES).map(function (key) {
                        return { label: SERIES[key].label, data: CHART[key], backgroundColor: SERIES[key].color, stack: 'total' };
                    })
                },
                options: {
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) { return ctx.dataset.label + ' : ' + eur(ctx.parsed.y); },
                                footer: function (items) {
                                    var total = items.reduce(function (s, i) { return s + i.parsed.y; }, 0);
                                    return 'Total débité TTC : ' + eur(total);
                                }
                            }
                        }
                    },
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true, beginAtZero: true, ticks: { callback: function (v) { return eur(v); } } }
                    }
                }
            });
        });
    </script>
@stop
