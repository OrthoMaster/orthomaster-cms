@extends('voyager::master')

@section('page_title', ' Suivi qualité')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-check"></i> Suivi qualité des formations
        </h1>
        <button type="button" class="btn btn-success btn-add-new" data-toggle="modal" data-target="#send-modal">
            <i class="voyager-paper-plane"></i> <span>Envoyer un questionnaire</span>
        </button>
    </div>
@stop

@php
    $fmt = fn ($v, $decimals = 1) => $v === null ? '—' : number_format($v, $decimals, ',', ' ');
@endphp

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            <div class="col-lg-12">

                {{-- ============ PÉRIODE + EXPORT ============ --}}
                <div class="row row-eq-height">
                    <div class="col-md-8 col-sm-12">
                        <div class="panel panel-bordered">
                            <div class="panel-heading">
                                <h3 class="panel-title"><i class="voyager-calendar"></i> Période</h3>
                            </div>
                            <div class="panel-body">
                                <form class="row" action="{{ route('dashboard.lessons.suivi') }}" method="GET" id="filters">
                                    <div class="col-sm-5">
                                        <label for="from">Du</label>
                                        <input type="date" class="form-control" name="from" id="from" value="{{ $filters['from'] }}">
                                    </div>
                                    <div class="col-sm-5">
                                        <label for="to">Au</label>
                                        <input type="date" class="form-control" name="to" id="to" value="{{ $filters['to'] }}">
                                    </div>
                                    <div class="col-sm-2 filter-actions">
                                        <button type="submit" class="btn btn-primary">Appliquer</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 col-sm-12">
                        <div class="panel panel-bordered">
                            <div class="panel-heading">
                                <h3 class="panel-title"><i class="voyager-download"></i> Export Excel</h3>
                            </div>
                            <div class="panel-body export-actions">
                                <a href="#" class="btn btn-success js-export" data-url="{{ route('dashboard.lessons.suivi.export') }}">
                                    <i class="voyager-star-two"></i> Satisfaction
                                </a>
                                <a href="#" class="btn btn-success js-export" data-url="{{ route('dashboard.lessons.suivi.export-completion') }}">
                                    <i class="voyager-book"></i> Complétion
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ KPI ============ --}}
                <div class="row row-eq-height">
                    <div class="col-md-6 col-sm-12">
                        <div class="panel panel-bordered kpi-panel kpi-blue">
                            <div class="panel-body">
                                <i class="voyager-book kpi-icon"></i>
                                <div class="kpi-label">Taux de complétion</div>
                                <div class="kpi-split">
                                    <div class="kpi-part">
                                        <div class="kpi-sub">Paquets dont la progression dépasse {{ $minimum }} %</div>
                                        <div class="kpi-value">{{ $fmt($completion['rate']) }}{{ $completion['rate'] !== null ? ' %' : '' }}</div>
                                    </div>
                                    <div class="kpi-part">
                                        <div class="kpi-sub">Terminés / paquets achetés</div>
                                        <div class="kpi-value">{{ $completion['done'] }} / {{ $completion['total'] }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-sm-12">
                        <div class="panel panel-bordered kpi-panel kpi-green">
                            <div class="panel-body">
                                <i class="voyager-star-two kpi-icon"></i>
                                <div class="kpi-label">Satisfaction</div>
                                <div class="kpi-split">
                                    <div class="kpi-part">
                                        <div class="kpi-sub">Score moyen (6 questions notées)</div>
                                        <div class="kpi-value">{{ $fmt($survey['score'], 2) }}{{ $survey['score'] !== null ? ' / 5' : '' }}</div>
                                    </div>
                                    <div class="kpi-part">
                                        <div class="kpi-sub">Taux de satisfaction (globale ≥ {{ \App\Models\SatisfactionSurvey::SATISFIED_MIN }})</div>
                                        <div class="kpi-value">{{ $fmt($survey['satisfaction']) }}{{ $survey['satisfaction'] !== null ? ' %' : '' }}</div>
                                    </div>
                                </div>
                                <div class="kpi-foot">
                                    {{ $survey['answered'] }} réponse(s)
                                    · taux de réponse {{ $fmt($survey['response_rate']) }}{{ $survey['response_rate'] !== null ? ' %' : '' }} ({{ $survey['sent'] }} envoyé(s))
                                    · NPS {{ $survey['nps'] ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ QUESTIONS ============ --}}
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="voyager-bar-chart"></i> Moyenne par question</h3>
                    </div>
                    <div class="panel-body">
                        <table class="table table-striped database-tables">
                            <tbody>
                                @foreach ($survey['questions'] as $q)
                                    <tr>
                                        <td>{{ $q['label'] }}</td>
                                        <td class="score-cell">
                                            @if ($q['average'] !== null)
                                                <div class="bar"><span style="width: {{ $q['average'] * 20 }}%"></span></div>
                                                <b>{{ $fmt($q['average'], 2) }}</b>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ============ RÉPONSES ============ --}}
                @if ($responses->total() === 0)
                    <div class="alert alert-warning">
                        <strong>Aucune réponse sur cette période.</strong>
                    </div>
                @else
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="voyager-list"></i> Réponses ({{ $responses->total() }})</h3>
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-striped database-tables">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Apprenant</th>
                                            <th>Formation(s)</th>
                                            <th>Score</th>
                                            <th>Reco.</th>
                                            <th>Commentaires</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($responses as $r)
                                            <tr>
                                                <td class="nowrap">{{ \Illuminate\Support\Carbon::parse($r->answered_at)->setTimezone(config('app.display_timezone', 'Asia/Ho_Chi_Minh'))->format('d/m/Y') }}</td>
                                                <td>
                                                    <b>{{ $r->learner }}</b>
                                                    @if ($r->is_fake)
                                                        <span class="label label-default">Démo</span>
                                                    @endif
                                                    @if ($r->recontact_flg)
                                                        <span class="label label-danger">À recontacter</span>
                                                    @endif
                                                    <div class="muted">{{ $r->email }}</div>
                                                </td>
                                                <td>{!! nl2br(e($r->packages_label)) !!}</td>
                                                <td class="nowrap"><b>{{ $fmt($r->score, 2) }}</b> / 5</td>
                                                <td>{{ $r->recommend }}</td>
                                                <td class="comments">
                                                    @if ($r->difficulties_flg)
                                                        <div><span class="label label-warning">Difficultés</span> {{ $r->difficulties_text }}</div>
                                                    @endif
                                                    @if ($r->liked_text)
                                                        <div><span class="tag">Apprécié</span> {{ $r->liked_text }}</div>
                                                    @endif
                                                    @if ($r->suggestions_text)
                                                        <div><span class="tag">Suggestion</span> {{ $r->suggestions_text }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="table-navigation" style="margin-bottom: 2em">
                                    {{ $responses->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- ============ ENVOI IMMÉDIAT ============ --}}
    <div class="modal fade" id="send-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form class="modal-content" method="POST" action="{{ route('dashboard.lessons.suivi.send') }}" id="send-form">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fermer"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="voyager-paper-plane"></i> Envoyer un questionnaire maintenant</h4>
                </div>
                <div class="modal-body">
                    <label for="send-email">E-mail</label>
                    <input type="email" class="form-control" name="email" id="send-email" required autocomplete="off" placeholder="prenom.nom@exemple.fr">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success" id="send-submit">Envoyer</button>
                </div>
            </form>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
    @include('dashboard.lessons._styles')
    <style>
        /* --- Filtres --- */
        .filter-actions { padding-top: 25px; }
        .filter-actions .btn { margin: 0; width: 100%; }
        .page-title + .btn-add-new { margin-top: 18px; }

        /* --- Export : boutons alignés sur le bas des champs de la carte Période --- */
        .export-actions { display: flex; gap: 10px; }
        .export-actions .btn { flex: 1 1 0; margin: 25px 0 0; }

        /* --- KPI --- */
        .kpi-foot { margin-top: 12px; padding-top: 10px; border-top: 1px solid #eceff3; color: #8a9199; font-size: 12px; }

        /* --- Moyennes par question --- */
        .score-cell { width: 260px; white-space: nowrap; }
        .score-cell .bar { display: inline-block; width: 170px; height: 8px; margin-right: 10px; border-radius: 999px; background: #eceff3; vertical-align: middle; }
        .score-cell .bar span { display: block; height: 100%; border-radius: 999px; background: #2ECC71; }

        /* --- Réponses --- */
        .nowrap { white-space: nowrap; }
        .muted { color: #a4abb3; font-size: 12px; }
        .comments { max-width: 420px; font-size: 13px; }
        .comments > div + div { margin-top: 6px; }
        .tag { display: inline-block; padding: 0 8px; border-radius: 999px; background: #eceff3; color: #5a6170; font-size: 11px; font-weight: 600; }
    </style>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {
            // Les exports reprennent la période affichée dans les champs, même sans avoir cliqué sur Appliquer.
            $('.js-export').on('click', function (event) {
                event.preventDefault();
                window.location.href = $(this).data('url') + '?' + $('#filters').serialize();
            });

            /* ---------- Envoi immédiat ---------- */
            $('#send-modal').on('shown.bs.modal', function () { $('#send-email').trigger('focus'); });

            $('#send-form').on('submit', function () {
                $('#send-submit').prop('disabled', true).text('Envoi…');
            });

            $('#from').on('change', function () { $('#to').attr('min', $(this).val() || ''); });
            $('#to').on('change', function () { $('#from').attr('max', $(this).val() || ''); });
        });
    </script>
@stop
