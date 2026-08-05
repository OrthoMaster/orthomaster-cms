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
                                <label for="event_id">Événement</label>
                                <select class="form-control" name="event_id" id="event_id">
                                    <option value="">Tous les événements</option>
                                    @foreach ($events as $e)
                                        <option value="{{ $e->id }}" data-code="{{ $e->code }}"
                                            @selected($filters['event_id'] == $e->id)>
                                            {{ $e->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="past_event_id">Édition</label>
                                <select class="form-control" name="past_event_id" id="past_event_id">
                                    <option value="">Toutes les éditions</option>
                                    @foreach ($pastEvents as $pe)
                                        <option value="{{ $pe->id }}" data-event="{{ $pe->event_id }}"
                                            @selected($filters['past_event_id'] == $pe->id)>
                                            {{ $pe->text }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

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
                                <button type="button" id="btn-share" class="btn btn-warning pull-right"
                                        style="margin-right:8px; display:none">
                                    <i class="voyager-share"></i> <span>Copier le lien de partage</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ============ KPI ============ --}}
                @include('dashboard.medias._kpis')

                {{-- ============ CHART 1 ============ --}}
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title">Vues sur la période</h3>
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
                @include('dashboard.medias._table', ['canEdit' => true])

            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
    @include('dashboard.medias._styles')
@stop

@section('javascript')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        var CHART       = @json($chart);
        var SHARE_BASE  = '{{ url('medias/shared') }}';

        $(document).ready(function () {

            /* ---------- Événement + édition (cascade) ---------- */
            var $event      = $('#event_id'),
                $pastEvent  = $('#past_event_id'),
                allEditions = $pastEvent.find('option[data-event]').clone();

            function refreshEditions() {
                var eid     = $event.val(),
                    current = $pastEvent.val();

                $pastEvent.find('option[data-event]').remove();

                allEditions.each(function () {
                    if (!eid || $(this).data('event') == eid) {
                        $pastEvent.append($(this).clone());
                    }
                });

                $pastEvent.val($pastEvent.find('option[value="' + current + '"]').length ? current : '');
                $pastEvent.trigger('change.select2');
            }

            /* ---------- Bouton de partage ---------- */
            function refreshShareButton() {
                var code = $event.find('option:selected').data('code');

                if ($event.val() && code) {
                    $('#btn-share').data('url', SHARE_BASE + '/' + code).show();
                } else {
                    $('#btn-share').hide();
                }
            }

            $('#btn-share').on('click', function () {
                var url  = $(this).data('url'),
                    $btn = $(this),
                    $lbl = $btn.find('span'),
                    old  = $lbl.text();

                function done() {
                    $lbl.text('Lien copié !');
                    setTimeout(function () { $lbl.text(old); }, 2000);
                }

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(url).then(done);
                } else {
                    var $tmp = $('<textarea>').val(url).css({ position: 'fixed', opacity: 0 }).appendTo('body');
                    $tmp[0].select();
                    document.execCommand('copy');
                    $tmp.remove();
                    done();
                }
            });

            $event.select2({ width: '100%' });
            $pastEvent.select2({ width: '100%' });

            $event.on('change', function () {
                refreshEditions();
                refreshShareButton();
                resetDependents();
            });

            $pastEvent.on('change', resetDependents);

            /* ---------- Auteur ---------- */
            function eventParams() {
                return {
                    event_id: $event.val() || '',
                    past_event_id: $pastEvent.val() || ''
                };
            }

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
                    data: function (params) {
                        return $.extend({ q: params.term }, eventParams());
                    },
                    processResults: function (data) { return { results: data }; },
                    cache: true
                }
            });

            /* ---------- Vidéo ---------- */
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
                            return $.extend({ q: params.term, expert_id: $('#expert_id').val() }, eventParams());
                        },
                        processResults: function (data) { return { results: data }; },
                        cache: true
                    }
                });
            }

            function resetDependents() {
                $('#expert_id').val(null).find('option[value!=""]').remove();
                $('#expert_id').trigger('change.select2');
                $('#content_id').val(null).find('option[value!=""]').remove();
                initVideoSelect();
            }

            initVideoSelect();

            $('#expert_id').on('change', function () {
                $('#content_id').val(null).find('option[value!=""]').remove();
                initVideoSelect();
            });

            refreshEditions();
            refreshShareButton();

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
                        { label: 'Vues',        data: CHART.professions.views,   backgroundColor: '#2ECC71' },
                        { label: 'Utilisateurs', data: CHART.professions.viewers, backgroundColor: '#E74C3C' }
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