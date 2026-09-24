@extends('voyager::master')

@section('page_title', ' Répartition des points')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-cannon"></i> Répartition des points
            <small>{{ $ad->title }}</small>
        </h1>
        <a href="{{ route('dashboard.video-ads') }}" class="btn btn-default">
            <i class="voyager-double-left"></i> <span>Retour au tableau de bord</span>
        </a>
    </div>
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        @include('voyager::alerts')

        <form action="{{ route('dashboard.video-ads.targets.store', $ad->id) }}" method="POST" id="targets-form">
            @csrf

            <div class="row">
                <div class="col-md-8">

                    {{-- ============ ENVELOPPE ============ --}}
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Enveloppe</h3>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="points_total">Points souscrits</label>
                                        <input type="number" class="form-control" id="points_total" name="points_total"
                                            min="0" step="10" value="{{ old('points_total', $pointsTotal) }}"
                                            required>
                                        <p class="help-block">
                                            Chaque axe dispose de l'enveloppe entière :
                                            <b><span id="per-axis">{{ $pointsTotal }}</span></b> points
                                            pour les professions, autant pour les centres d'intérêt.
                                        </p>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="effective_from">Date de prise d'effet</label>
                                        <input type="date" class="form-control" id="effective_from" name="effective_from"
                                            value="{{ old('effective_from', $effectiveFrom) }}" required>
                                        <p class="help-block" id="effective-warning"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ============ AXES ============ --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="panel panel-bordered">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Professions
                                        <span class="axis-counter" data-axis="profession">
                                            <b class="axis-used">0</b> / <span class="axis-max">{{ $pointsTotal }}</span>
                                        </span>
                                    </h3>

                                </div>
                                <div class="panel-body target-list">
                                    @foreach ($professions as $p)
                                        <div class="target-row">
                                            <span class="target-name">{{ $p->name }}</span>
                                            <input type="number" class="form-control target-input" data-axis="profession"
                                                name="profession[{{ $p->id }}]" min="0" step="1"
                                                value="{{ old('profession.' . $p->id, $points['profession'][$p->id] ?? 0) }}">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="panel panel-bordered">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Centres d'intérêt
                                        <span class="axis-counter" data-axis="topic">
                                            <b class="axis-used">0</b> / <span class="axis-max">{{ $pointsTotal }}</span>
                                        </span>
                                    </h3>
                                </div>
                                <div class="panel-body target-list">
                                    @foreach ($topics as $t)
                                        <div class="target-row">
                                            <span class="target-name">{{ $t->name }}</span>
                                            <input type="number" class="form-control target-input" data-axis="topic"
                                                name="topic[{{ $t->id }}]" min="0" step="1"
                                                value="{{ old('topic.' . $t->id, $points['topic'][$t->id] ?? 0) }}">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ HISTORIQUE ============ --}}
                <div class="col-md-4">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="voyager-list"></i> Historique</h3>
                        </div>
                        <div class="panel-body history-list">
                            @if (empty($history))
                                <p class="history-empty">
                                    Aucune répartition enregistrée. Les publicités sans points
                                    concourent à poids égal auprès de tous les profils.
                                </p>
                            @else
                                @foreach ($history as $entry)
                                    <div class="history-row is-{{ $entry['status'] }}">
                                        <div class="history-head">
                                            <span class="history-date">{{ $entry['label'] }}</span>
                                            <span class="history-badge">
                                                @switch($entry['status'])
                                                    @case('current')
                                                        En vigueur
                                                    @break

                                                    @case('future')
                                                        À venir
                                                    @break

                                                    @default
                                                        Passée
                                                @endswitch
                                            </span>
                                        </div>
                                        <div class="history-meta">
                                            {{ $entry['points_used'] }} / {{ $entry['points_total'] }} points
                                            &middot; {{ $entry['targets'] }}
                                            {{ $entry['targets'] > 1 ? 'cibles' : 'cible' }}
                                        </div>
                                        @if ($entry['status'] === 'future')
                                            <button type="button" class="btn btn-xs btn-danger history-delete"
                                                data-date="{{ $entry['effective_from'] }}">
                                                <i class="voyager-trash"></i> Supprimer
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </form>

        {{-- Suppression : formulaire distinct, hors du formulaire principal --}}
        <form action="" method="POST" id="delete-form" style="display:none">
            @csrf
            @method('DELETE')
        </form>
    </div>

    <div class="action-wrapper-absolute">
        <a href="{{ route('dashboard.video-ads') }}" class="btn btn-default">Annuler</a>
        <button type="submit" form="targets-form" class="btn btn-primary">
            <i class="voyager-check"></i> <span>Enregistrer</span>
        </button>
    </div>
@stop

@section('css')
    <style>
        .action-wrapper-absolute {
            position: fixed;
            z-index: 9999;
            right: 40px;
            bottom: 30px;
            display: flex;
            gap: 8px;
            padding: 10px 12px;
            background: rgba(255, 255, 255, .96);
            border: 1px solid #e3e6ec;
            border-radius: 8px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .12);
        }

        .page-content.edit-add {
            padding-bottom: 90px;
        }

        /* --- Axes --- */
        .axis-counter {
            float: right;
            font-size: 12px;
            font-weight: 600;
            color: #8a9199;
        }

        .axis-counter.is-over {
            color: #E74C3C;
        }

        .axis-counter.is-over .axis-used {
            font-size: 14px;
        }

        .target-list {
            padding: 0;
        }

        .target-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 2px 4px;
        }

        .target-row+.target-row {
            border-top: 1px solid #f0f2f5;
        }

        .target-name {
            flex: 1;
            font-size: 13px;
            color: #37474f;
        }

        .target-input {
            width: 86px;
            text-align: right;
        }

        .target-input.is-set {
            border-color: #22A7F0;
            font-weight: 600;
        }

        /* --- Historique --- */
        .history-list {
            max-height: 520px;
            overflow-y: auto;
            padding: 0;
        }

        .history-empty {
            margin: 0;
            padding: 18px 16px;
            font-size: 13px;
            color: #8a9199;
            line-height: 1.5;
        }

        .history-row {
            padding: 12px 16px;
            border-left: 3px solid transparent;
        }

        .history-row+.history-row {
            border-top: 1px solid #f0f2f5;
        }

        .history-row.is-current {
            border-left-color: #2ECC71;
            background: #f7fdf9;
        }

        .history-row.is-future {
            border-left-color: #22A7F0;
            background: #f7fbfe;
        }

        .history-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .history-date {
            font-size: 14px;
            font-weight: 600;
            color: #37474f;
        }

        .history-badge {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #8a9199;
        }

        .is-current .history-badge {
            color: #2ECC71;
        }

        .is-future .history-badge {
            color: #22A7F0;
        }

        .history-meta {
            margin-top: 3px;
            font-size: 12px;
            color: #8a9199;
        }

        .history-delete {
            margin-top: 8px;
        }
    </style>
@stop

@section('javascript')
    <script>
        $(document).ready(function() {

            var EXISTING_DATES = @json(collect($history)->pluck('effective_from')->all());

            /* ---------- Compteurs par axe ---------- */
            function perAxis() {
                return parseInt($('#points_total').val(), 10) || 0;
            }

            function refreshCounters() {
                var max = perAxis();

                $('#per-axis').text(max);
                $('.axis-max').text(max);

                $('.axis-counter').each(function() {
                    var axis = $(this).data('axis'),
                        used = 0;

                    $('.target-input[data-axis="' + axis + '"]').each(function() {
                        used += parseInt($(this).val(), 10) || 0;
                    });

                    $(this).find('.axis-used').text(used);
                    $(this).toggleClass('is-over', used > max);
                });
            }

            /* ---------- Mise en évidence des cibles dotées ---------- */
            function refreshInputs() {
                $('.target-input').each(function() {
                    $(this).toggleClass('is-set', (parseInt($(this).val(), 10) || 0) > 0);
                });
            }

            $('.target-input, #points_total').on('input change', function() {
                refreshCounters();
                refreshInputs();
            });

            /* ---------- Date déjà utilisée ---------- */
            $('#effective_from').on('change', function() {
                var used = EXISTING_DATES.indexOf($(this).val()) !== -1;

                $('#effective-warning')
                    .toggleClass('text-warning', used)
                    .text(used ?
                        'Une répartition existe déjà à cette date : elle sera remplacée.' :
                        '');
            }).trigger('change');

            /* ---------- Garde-fou à l'envoi ---------- */
            $('#targets-form').on('submit', function(e) {
                if ($('.axis-counter.is-over').length === 0) {
                    return;
                }

                e.preventDefault();
                alert('Chaque axe est limité à ' + perAxis() + ' points.');
            });

            /* ---------- Suppression ---------- */
            $('.history-delete').on('click', function() {
                var date = $(this).data('date');

                if (!confirm('Supprimer la répartition du ' + date + ' ?')) {
                    return;
                }

                $('#delete-form')
                    .attr('action', '{{ route('dashboard.video-ads.targets', $ad->id) }}/' + date)
                    .submit();
            });

            refreshCounters();
            refreshInputs();
        });
    </script>
@stop
