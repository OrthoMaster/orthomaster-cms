@extends('voyager::master')

@section('page_title', ' Leçons')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-book"></i> Leçons
        </h1>
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
                        <form class="row" action="{{ route('dashboard.lessons.index') }}" method="GET">
                            <div class="col-md-4">
                                <label for="training_id">Formation</label>
                                <select class="form-control" name="training_id" id="training_id">
                                    <option value="">Toutes les formations</option>
                                    @foreach ($trainings as $t)
                                        <option value="{{ $t->id }}" @selected($filters['training_id'] == $t->id)>
                                            {{ $t->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="package_id">Paquet</label>
                                <select class="form-control" name="package_id" id="package_id">
                                    <option value="">Tous les paquets</option>
                                    @foreach ($packages as $p)
                                        <option value="{{ $p->id }}" data-training="{{ $p->training_id }}"
                                            @selected($filters['package_id'] == $p->id)>
                                            {{ $p->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="lesson_id">Leçon</label>
                                <select class="form-control" name="lesson_id" id="lesson_id">
                                    <option value=""></option>
                                    @if ($filters['lesson_id'])
                                        <option value="{{ $filters['lesson_id'] }}" selected>{{ $selectedLesson }}</option>
                                    @endif
                                </select>
                            </div>

                            <div class="col-md-2" style="margin-top:10px">
                                <label for="granularity">Granularité</label>
                                <select class="form-control" name="granularity" id="granularity">
                                    <option value="day" @selected($filters['granularity'] === 'day')>Jour</option>
                                    <option value="month" @selected($filters['granularity'] === 'month')>Mois</option>
                                    <option value="year" @selected($filters['granularity'] === 'year')>Année</option>
                                </select>
                            </div>

                            <div class="col-md-2" style="margin-top:10px">
                                <label for="from">Du</label>
                                <input type="date" class="form-control" name="from" id="from"
                                    min="2026-01-01" value="{{ $filters['from'] }}">
                            </div>

                            <div class="col-md-2" style="margin-top:10px">
                                <label for="to">Au</label>
                                <input type="date" class="form-control" name="to" id="to"
                                    min="2026-01-01" value="{{ $filters['to'] }}">
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
                @include('dashboard.lessons._kpis')

                {{-- ============ CHART 1 ============ --}}
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="voyager-line-chart"></i> Vues sur la période</h3>
                    </div>
                    <div class="panel-body">
                        <div style="height:300px"><canvas id="chartTimeline"></canvas></div>
                    </div>
                </div>

                {{-- ============ CHART 2 + 2bis ============ --}}
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
                                <h3 class="panel-title"><i class="voyager-calendar"></i> Répartition par jour de la semaine</h3>
                            </div>
                            <div class="panel-body">
                                <div style="height:320px"><canvas id="chartWeekdays"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ CHART 3 ============ --}}
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="voyager-people"></i> Vues par profession</h3>
                    </div>
                    <div class="panel-body">
                        <div style="height:320px"><canvas id="chartProfessions"></canvas></div>
                    </div>
                </div>

                {{-- ============ TABLE ============ --}}
                @include('dashboard.lessons._table')

            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
    @include('dashboard.lessons._styles')
@stop

@section('javascript')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        var CHART = @json($chart);

        $(document).ready(function () {

            /* ---------- Formation + paquet (cascade) ---------- */
            var $training   = $('#training_id'),
                $package    = $('#package_id'),
                allPackages = $package.find('option[data-training]').clone();

            function refreshPackages() {
                var tid     = $training.val(),
                    current = $package.val();

                $package.find('option[data-training]').remove();

                allPackages.each(function () {
                    if (!tid || $(this).data('training') == tid) {
                        $package.append($(this).clone());
                    }
                });

                $package.val($package.find('option[value="' + current + '"]').length ? current : '');
                $package.trigger('change.select2');
            }

            $training.select2({ width: '100%' });
            $package.select2({ width: '100%' });

            /* ---------- Leçon (dépend de la formation et du paquet) ---------- */
            function scopeParams() {
                return {
                    training_id: $training.val() || '',
                    package_id: $package.val() || ''
                };
            }

            function initLessonSelect() {
                if ($('#lesson_id').hasClass('select2-hidden-accessible')) {
                    $('#lesson_id').select2('destroy');
                }

                $('#lesson_id').select2({
                    width: '100%',
                    placeholder: 'Toutes les leçons',
                    allowClear: true,
                    minimumInputLength: 3,
                    language: {
                        inputTooShort: function () { return 'Saisissez au moins 3 caractères'; },
                        noResults: function () { return 'Aucun résultat'; },
                        searching: function () { return 'Recherche…'; }
                    },
                    ajax: {
                        url: '{{ route('dashboard.lessons.search-lessons') }}',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return $.extend({ q: params.term }, scopeParams());
                        },
                        processResults: function (data) { return { results: data }; },
                        cache: true
                    }
                });
            }

            function resetLesson() {
                $('#lesson_id').val(null).find('option[value!=""]').remove();
                initLessonSelect();
            }

            $training.on('change', function () {
                refreshPackages();
                resetLesson();
            });

            $package.on('change', resetLesson);

            initLessonSelect();
            refreshPackages();

            /* ---------- Bornes dynamiques des dates ---------- */
            $('#from').on('change', function () {
                $('#to').attr('min', $(this).val() || '2026-01-01');
            });

            $('#to').on('change', function () {
                $('#from').attr('max', $(this).val() || '');
            });

            /* ---------- Charts ---------- */
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

            new Chart(document.getElementById('chartWeekdays'), {
                type: 'bar',
                data: {
                    labels: CHART.weekdays.labels,
                    datasets: [{ label: 'Vues', data: CHART.weekdays.views, backgroundColor: '#F39C12' }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });

            new Chart(document.getElementById('chartProfessions'), {
                type: 'bar',
                data: {
                    labels: CHART.professions.labels,
                    datasets: [
                        { label: 'Vues',          data: CHART.professions.views,   backgroundColor: '#2ECC71' },
                        { label: 'Utilisateurs',  data: CHART.professions.viewers, backgroundColor: '#E74C3C' }
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