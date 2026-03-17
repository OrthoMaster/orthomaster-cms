@extends('voyager::master')

@section('page_title', 'Analyse')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-play"></i> Contenu
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content">
        @include('voyager::alerts')

        <div class="analytics-container">
            <div class="row">
                <div class="col-md-4">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="voyager-search"></i> Filtres</h3>
                        </div>
                        <div class="panel-body">
                            <form class="row" action="{{ route('reports.content.profession') }}" method="GET">
                                <div class="col-md-12">
                                    <label for="display_name">Contenu ID</label>
                                    <sub>Laissez vide si vous effectuez une recherche complète.</sub>
                                    <input type="text" class="form-control" name="content_id" value="{{ request('content_id') }}"
                                        placeholder="Contenu ID">
                                </div>
                                <div class="col-md-12">
                                    <label for="type">Type</label>
                                    <select class="form-control select2" name="type">
                                        <option value="flash" @selected(request('type') === 'flash')>Médias</option>
                                        <option value="lesson" @selected(request('type') === 'lesson')>Leçon</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="display_name">De</label>
                                    <input type="date" class="form-control" name="from" placeholder="dd/mm/yyyy"
                                        value="{{ request('from') }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="display_name">À</label>
                                    <input type="date" class="form-control" name="to" placeholder="dd/mm/yyyy"
                                        value="{{ request('to') }}">
                                </div>
                                
                                <div class="col-md-12" style="margin-bottom: 0">
                                    <button type="submit" class="btn btn-primary pull-right">
                                        Cherchez
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    @if (isset($data) && !($data['labels']->count() > 0))
                        <div class="alert alert-warning">
                            <strong>Aucune information n'est disponible !</strong>
                            <p>Vous pouvez modifier les filtres pour trouver les bonnes informations.</p>
                        </div>
                    @else
                        <div class="panel panel-bordered">
                            <div class="panel-heading"
                                style="display: flex; justify-content: space-between; align-items: center;">
                                <h3 class="panel-title">{{ $content ? $content->title : 'Classer les vues par profession.' }}</h3>
                            </div>
                            <div class="panel-body">
                                <canvas id="contentChart"></canvas>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const chart = document.getElementById('contentChart');
        new Chart(chart, {
            type: 'bar',
            data: {
                labels: @json($data['labels']),
                datasets: [{
                    label: 'Nombre de vues',
                    data: @json($data['data']),
                    backgroundColor: 'rgba(40, 167, 69, 0.6)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    </script>
@stop
