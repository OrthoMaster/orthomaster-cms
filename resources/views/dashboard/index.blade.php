@extends('voyager::master')

@section('content')
    <div class="page-content">
        <div class="analytics-container">
            <div class="row">
                <div class="col-md-12 col-lg-6">
                    <div class="panel panel-bordered">
                        <div class="panel-heading"
                            style="display: flex; justify-content: space-between; align-items: center;">
                            <h3 class="panel-title">Nombre de connexions des utilisateurs</h3>
                            <div class="btn-group" style="margin-right: 15px">
                                <button type="button" class="btn btn-sm btn-info dropdown-toggle" data-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
                                    <span id="selectedPeriod">7 jours</span> <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right">
                                    <li><a href="#" data-days="7">7 jours</a></li>
                                    <li><a href="#" data-days="30">1 mois</a></li>
                                    <li><a href="#" data-days="90">3 mois</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="panel-body">
                            <canvas id="dailyAppOpenChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-lg-3">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Analyse de la conversion des comptes</h3>
                        </div>
                        <div class="panel-body">
                            <canvas id="chart2"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-lg-3">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Analyse comparative des plateformes</h3>
                        </div>
                        <div class="panel-body">
                            <canvas id="chart3"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                {{-- Media Views --}}
                <div class="col-md-12 col-lg-6">
                    <div class="panel panel-bordered">
                        <div class="panel-heading"
                            style="display: flex; justify-content: space-between; align-items: center;">
                            <h3 class="panel-title">Nombre de vues du média - 7 derniers jours</h3>
                        </div>
                        <div class="panel-body">
                            <canvas id="mediaViewsChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Lesson Views --}}
                <div class="col-md-12 col-lg-6">
                    <div class="panel panel-bordered">
                        <div class="panel-heading"
                            style="display: flex; justify-content: space-between; align-items: center;">
                            <h3 class="panel-title">Nombre de vues du leçon - 7 derniers jours</h3>
                        </div>
                        <div class="panel-body">
                            <canvas id="lessonViewsChart"></canvas>
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
        let dailyAppOpenChartInstance;
        const dailyAppOpenCanvas = document.getElementById('dailyAppOpenChart');

        function updateChart(labels, data) {
            if (dailyAppOpenChartInstance) {
                dailyAppOpenChartInstance.destroy();
            }

            dailyAppOpenChartInstance = new Chart(dailyAppOpenCanvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nombre de connexions',
                        data: data,
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
        }

        updateChart(@json($dailyAppOpens->pluck('formatted_date')), @json($dailyAppOpens->pluck('count')));

        document.querySelectorAll('.dropdown-menu a').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const days = this.getAttribute('data-days');
                const text = this.textContent;

                document.getElementById('selectedPeriod').textContent = text;

                fetch(`{{ route('dashboard.app-opens-chart-data') }}?days=${days}`)
                    .then(response => response.json())
                    .then(data => {
                        updateChart(data.labels, data.counts);
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Erreur lors du chargement des données');
                    });
            });
        });

        // Chart 2 - User Active
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

        // Chart 3 - User Agents
        const chart3 = document.getElementById('chart3');
        new Chart(chart3, {
            type: 'doughnut',
            data: {
                labels: @json($userAgents['label']),
                datasets: [{
                    data: @json($userAgents['data']),
                    backgroundColor: [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 205, 86)'
                    ],
                    hoverOffset: 4
                }]
            },
        });

        // Media Views
        const chartMediaViews = document.getElementById('mediaViewsChart');
        new Chart(chartMediaViews, {
            type: 'bar',
            data: {
                labels: @json($mediaViews['labels']),
                datasets: [{
                    label: 'Nombre de vues',
                    data: @json($mediaViews['data']),
                    backgroundColor: 'rgba(255, 0, 0, 0.6)',
                    borderColor: 'rgba(255, 0, 0, 1)',
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

        // Lesson Views
        const chartLessonViews = document.getElementById('lessonViewsChart');
        new Chart(chartLessonViews, {
            type: 'bar',
            data: {
                labels: @json($lessonViews['labels']),
                datasets: [{
                    label: 'Nombre de vues',
                    data: @json($lessonViews['data']),
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
