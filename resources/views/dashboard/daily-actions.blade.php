@extends('voyager::master')

@section('page_title', ' Actions du jour')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-activity"></i> Actions du jour
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            <div class="col-lg-12">

                {{-- ============ KPI ============ --}}
                <div class="row row-eq-height">
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="panel panel-bordered kpi-panel kpi-blue">
                            <div class="panel-body">
                                <i class="voyager-music kpi-icon"></i>
                                <div class="kpi-label">Podcasts publiés</div>
                                <div class="kpi-value">{{ number_format($summary['podcasts'], 0, ',', ' ') }}</div>
                                <a href="{{ route('voyager.podcasts.index') }}" class="kpi-link">
                                    Voir tout <i class="voyager-angle-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-2 col-md-6 col-sm-6">
                        <div class="panel panel-bordered kpi-panel kpi-purple">
                            <div class="panel-body">
                                <i class="voyager-question kpi-icon"></i>
                                <div class="kpi-label">Quiz publiés</div>
                                <div class="kpi-value">{{ number_format($summary['quizzes'], 0, ',', ' ') }}</div>
                                <a href="{{ route('voyager.daily-questions.index') }}" class="kpi-link">
                                    Voir tout <i class="voyager-angle-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="panel panel-bordered kpi-panel kpi-green">
                            <div class="panel-body">
                                <i class="voyager-images kpi-icon"></i>
                                <div class="kpi-label">Infographies publiées</div>
                                <div class="kpi-value">{{ number_format($summary['infographics'], 0, ',', ' ') }}</div>
                                <a href="{{ route('infographics.index') }}" class="kpi-link">
                                    Voir tout <i class="voyager-angle-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6 col-sm-6">
                        <div class="panel panel-bordered kpi-panel kpi-orange">
                            <div class="panel-body">
                                <i class="voyager-people kpi-icon"></i>
                                <div class="kpi-label">Utilisateurs</div>

                                <div class="kpi-split">
                                    <div class="kpi-part">
                                        <div class="kpi-sub">Avec centre d'intérêt</div>
                                        <div class="kpi-value">
                                            {{ number_format($summary['users_with_topics'], 0, ',', ' ') }}
                                            <small class="kpi-rate">{{ $summary['users_rate'] }} %</small>
                                        </div>
                                    </div>
                                    <div class="kpi-part">
                                        <div class="kpi-sub">Notifications activées</div>
                                        <div class="kpi-value">
                                            {{ number_format($summary['users_with_notifications'], 0, ',', ' ') }}
                                            <small class="kpi-rate">{{ $summary['notifications_rate'] }} %</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="kpi-sub kpi-total">
                                    sur {{ number_format($summary['total_users'], 0, ',', ' ') }} utilisateurs
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ RÉPARTITION ============ --}}
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <i class="voyager-list"></i> Répartition par centre d'intérêt
                        </h3>
                    </div>
                    <div class="panel-body">

                        @if ($distribution['rows']->isEmpty())
                            <div class="alert alert-warning" style="margin-bottom:0">
                                <strong>Aucun centre d'intérêt enregistré.</strong>
                            </div>
                        @else
                            <p class="help-block" style="margin-top:0">
                                Les pourcentages sont calculés sur le total de chaque colonne.
                                L'intensité de la couleur reflète le poids relatif de chaque ligne.
                            </p>

                            <div class="table-responsive">
                                <table class="table distribution-table">
                                    <thead>
                                        <tr>
                                            <th>Centre d'intérêt</th>
                                            <th class="col-podcasts">
                                                Podcasts
                                                <span class="col-total">{{ number_format($distribution['totals']['podcasts'], 0, ',', ' ') }}</span>
                                            </th>
                                            <th class="col-quizzes">
                                                Quiz
                                                <span class="col-total">{{ number_format($distribution['totals']['quizzes'], 0, ',', ' ') }}</span>
                                            </th>
                                            <th class="col-infographics">
                                                Infographies
                                                <span class="col-total">{{ number_format($distribution['totals']['infographics'], 0, ',', ' ') }}</span>
                                            </th>
                                            <th class="col-users">
                                                Sélections
                                                <span class="col-total">{{ number_format($distribution['totals']['users'], 0, ',', ' ') }}</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($distribution['rows'] as $row)
                                            <tr>
                                                <td class="topic-name">{{ $row['name'] }}</td>

                                                @foreach (['podcasts', 'quizzes', 'infographics', 'users'] as $key)
                                                    @php
                                                        $rate = $row[$key . '_rate'];

                                                        // Fond léger en dessous de 10 %, puis intensité
                                                        // proportionnelle sur la plage restante
                                                        $alpha = $rate <= 10
                                                            ? 0.1
                                                            : round(0.1 + (min($rate, 100) - 10) / 90 * 0.9, 3);
                                                    @endphp
                                                    <td class="cell cell-{{ $key }} {{ $alpha > 0.55 ? 'is-strong' : '' }}"
                                                        style="--alpha: {{ $alpha }}"
                                                        title="{{ $row[$key] }} élément(s)">
                                                        {{ $rate }} %
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                    </div>
                </div>

            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        /* --- Colonnes de même hauteur --- */
        .row-eq-height { display: flex; flex-wrap: wrap; }
        .row-eq-height > [class*='col-'] { display: flex; }
        .row-eq-height .panel { width: 100%; display: flex; flex-direction: column; }
        .row-eq-height .panel-body { flex: 1 1 auto; }

        @media (max-width: 991px) {
            .row-eq-height { display: block; }
            .row-eq-height > [class*='col-'] { display: block; }
        }

        /* --- Cartes KPI --- */
        .kpi-panel { position: relative; overflow: hidden; border-left: 4px solid #ddd; }
        .kpi-panel .panel-body { padding: 18px 20px; }

        .kpi-label {
            font-size: 12px; font-weight: 600; text-transform: uppercase;
            letter-spacing: .04em; color: #8a9199; margin-bottom: 6px;
            padding-right: 40px; line-height: 1.35; min-height: 32px;
        }

        .kpi-value { font-size: 30px; font-weight: 700; line-height: 1.1; }
        .kpi-rate { font-size: 15px; font-weight: 600; margin-left: 6px; opacity: .75; }
        .kpi-sub { margin-top: 6px; font-size: 12px; color: #a4abb3; }
        .kpi-icon { position: absolute; top: 16px; right: 16px; font-size: 34px; opacity: .16; }

        .kpi-blue   { border-left-color: #22A7F0; }
        .kpi-purple { border-left-color: #955BA5; }
        .kpi-green  { border-left-color: #2ECC71; }
        .kpi-orange { border-left-color: #F39C12; }

        .kpi-blue   .kpi-value, .kpi-blue   .kpi-icon { color: #22A7F0; }
        .kpi-purple .kpi-value, .kpi-purple .kpi-icon { color: #955BA5; }
        .kpi-green  .kpi-value, .kpi-green  .kpi-icon { color: #2ECC71; }
        .kpi-orange .kpi-value, .kpi-orange .kpi-icon { color: #F39C12; }

        .kpi-link {
            display: inline-block;
            margin-top: 10px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .kpi-link i { font-size: 10px; }

        .kpi-blue   .kpi-link { color: #22A7F0; }
        .kpi-purple .kpi-link { color: #955BA5; }
        .kpi-green  .kpi-link { color: #2ECC71; }

        .kpi-link:hover, .kpi-link:focus { text-decoration: underline; }

        /* --- KPI à deux chiffres --- */
        .kpi-split { display: flex; }
        .kpi-part { flex: 1 1 50%; }
        .kpi-part + .kpi-part { border-left: 1px solid #eceff3; padding-left: 12px; }

        .kpi-sub {
            font-size: 11px; text-transform: uppercase; letter-spacing: .03em;
            color: #a4abb3; margin-bottom: 4px; line-height: 1.35;
            min-height: 30px; padding-right: 8px;
        }

        .kpi-split .kpi-value { font-size: 22px; }
        .kpi-split .kpi-rate { font-size: 12px; margin-left: 4px; }

        .kpi-total {
            margin-top: 10px;
            margin-bottom: 0;
            min-height: 0;
            text-transform: none;
        }

        /* --- Tableau de répartition --- */
        .distribution-table { margin-bottom: 0; }

        .distribution-table thead th {
            border-bottom: 2px solid #e3e6ec;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #8a9199;
            padding: 10px 12px;
            text-align: center;
        }

        .distribution-table thead th:first-child { text-align: left; }

        .col-total {
            display: block;
            margin-top: 3px;
            font-size: 16px;
            font-weight: 700;
            text-transform: none;
            letter-spacing: 0;
        }

        .distribution-table thead th.col-podcasts,
        .distribution-table thead th.col-podcasts .col-total { color: #22A7F0; }
        .distribution-table thead th.col-quizzes,
        .distribution-table thead th.col-quizzes .col-total { color: #955BA5; }
        .distribution-table thead th.col-users,
        .distribution-table thead th.col-users .col-total { color: #F39C12; }
        .distribution-table thead th.col-infographics,
        .distribution-table thead th.col-infographics .col-total { color: #2ECC71; }

        .cell {
            padding: 14px 12px;
            text-align: center;
            font-size: 16px;
            font-weight: 400;
            color: #37474f;
            transition: background-color .15s;
        }

        .cell.is-strong { color: #fff; }

        .cell-podcasts { background: rgba(34, 167, 240, var(--alpha, 0)); }
        .cell-quizzes  { background: rgba(149, 91, 165, var(--alpha, 0)); }
        .cell-users    { background: rgba(243, 156, 18, var(--alpha, 0)); }
        .cell-infographics { background: rgba(46, 204, 113, var(--alpha, 0)); }

        /* Fond soutenu : on passe le texte en blanc pour rester lisible */
        .cell.is-strong .cell-value,
        .cell.is-strong .cell-rate { color: #fff; }
        
    </style>
@stop