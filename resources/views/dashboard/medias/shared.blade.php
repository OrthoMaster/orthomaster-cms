@php
    $eventNames = $events->pluck('name')->implode(' · ');
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $eventNames }} — Rapport médias</title>

    <link rel="stylesheet" href="{{ voyager_asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}">
    @include('dashboard.medias._styles')

    <style>
        body { background: #f8f8f8; padding-bottom: 40px; }
        .shared-header { background: #fff; border-bottom: 1px solid #e3e6ec; padding: 18px 0; margin-bottom: 24px; }
        .shared-header h1 { margin: 0; font-size: 22px; }
        .shared-header small { color: #8a9199; }

        /* Événements imposés par le lien : affichés, non modifiables */
        .event-locked {
            display: flex; flex-wrap: wrap; gap: 6px;
            padding: 5px 8px; min-height: 34px;
            background: #f4f6f9; border: 1px solid #e3e6ec; border-radius: 4px;
        }
        .event-locked-item {
            padding: 2px 10px; border-radius: 12px;
            background: #fff; border: 1px solid #d6dce6;
            font-size: 12px; font-weight: 600; line-height: 20px;
        }
    </style>
</head>
<body>

<div class="shared-header">
    <div class="container-fluid">
        <h1>{{ $eventNames }} <small>— Rapport médias</small></h1>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">

            {{-- ============ FILTRES ============ --}}
            <div class="panel panel-bordered">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="voyager-search"></i> Filtres</h3>
                </div>
                <div class="panel-body">
                    <form class="row" action="{{ route('medias.shared', $code) }}" method="GET">
                        <div class="col-md-3">
                            <label>{{ $events->count() > 1 ? 'Événements' : 'Événement' }}</label>
                            <div class="event-locked">
                                @foreach ($events as $event)
                                    <span class="event-locked-item">{{ $event->name }}</span>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label for="past_event_id">Édition</label>
                            <select class="form-control" name="past_event_id" id="past_event_id">
                                <option value="">Toutes les éditions</option>
                                @foreach ($pastEvents as $pe)
                                    <option value="{{ $pe->id }}" @selected($filters['past_event_id'] == $pe->id)>
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
                            <input type="date" class="form-control" name="from" id="from" min="2026-01-01" value="{{ $filters['from'] }}">
                        </div>

                        <div class="col-md-2" style="margin-top:10px">
                            <label for="to">Au</label>
                            <input type="date" class="form-control" name="to" id="to" min="2026-01-01" value="{{ $filters['to'] }}">
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
            @include('dashboard.medias._kpis', ['showViewers' => false])

            {{-- ============ CHART 1 ============ --}}
            <div class="panel panel-bordered">
                <div class="panel-heading">
                    <h3 class="panel-title">Vues sur la période</h3>
                </div>
                <div class="panel-body">
                    <div style="height:300px"><canvas id="chartTimeline"></canvas></div>
                </div>
            </div>

            {{-- ============ TABLE ============ --}}
            @include('dashboard.medias._table', [
                'canEdit'     => false,
                'showViewers' => false,
                'showWatched' => false,
            ])

        </div>
    </div>
</div>

<script src="{{ voyager_asset('js/app.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    var CHART = @json($chart);

    $(document).ready(function () {

        $('#past_event_id').select2({ width: '100%' });

        // Les événements sont imposés côté serveur : seule l'édition est transmise
        function eventParams() {
            return { past_event_id: $('#past_event_id').val() || '' };
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
                url: '{{ route('medias.shared.search-experts', $code) }}',
                dataType: 'json',
                delay: 250,
                data: function (params) { return $.extend({ q: params.term }, eventParams()); },
                processResults: function (data) { return { results: data }; },
                cache: true
            }
        });

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
                    url: '{{ route('medias.shared.search-videos', $code) }}',
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

        initVideoSelect();

        $('#past_event_id, #expert_id').on('change', function () {
            $('#content_id').val(null).find('option[value!=""]').remove();
            initVideoSelect();
        });

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
    });
</script>

</body>
</html>