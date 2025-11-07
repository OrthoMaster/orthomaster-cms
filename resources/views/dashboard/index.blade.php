@extends('voyager::master')

@section('content')
    <div class="page-content">
        <div class="analytics-container">
            <div class="row">
                <div class="col-md-8">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Nombre de connexions des utilisateurs</h3>
                        </div>
                        <div class="panel-body">
                            <canvas id="dailyAppOpenChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Analyse de la conversion des comptes</h3>
                        </div>
                        <div class="panel-body">
                            <canvas id="chart2"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const dailyAppOpenChart = document.getElementById('dailyAppOpenChart');
        const daoc_labels = @json($dailyAppOpens->pluck('formatted_date'));
        const daoc_data = @json($dailyAppOpens->pluck('count'));
        new Chart(dailyAppOpenChart, {
            type: 'line',
            data: {
                labels: daoc_labels,
                datasets: [{
                    label: 'Nombre de connexions',
                    data: daoc_data,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    fill: true,
                    borderWidth: 2,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        const chart2 = document.getElementById('chart2');
        new Chart(chart2, {
            type: 'doughnut',
            data: {
                labels: @json($totalUsers['label']),
                datasets: [{
                    data: @json($totalUsers['data']),
                    backgroundColor: [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 205, 86)'
                    ],
                    hoverOffset: 4
                }]
            },
        });
    </script>
@stop
