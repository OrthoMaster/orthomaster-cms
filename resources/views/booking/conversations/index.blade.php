@extends('voyager::master')

@php
    use Carbon\Carbon;

    $tz = config('app.display_timezone', 'Europe/Paris');
    $fmt = fn ($value, $format = 'd/m/Y H:i') => $value ? Carbon::parse($value)->timezone($tz)->format($format) : '—';
@endphp

@section('page_title', 'Messagerie')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-chat"></i> Messagerie
    </h1>
    <a href="{{ route('booking.appointments.index') }}" class="btn btn-default">
        <i class="voyager-calendar"></i> Rendez-vous
    </a>
    <a href="{{ route('booking.experts.index') }}" class="btn btn-default">
        <i class="voyager-people"></i> Experts
    </a>
@stop

@section('css')
    <style>
        /* --- Filtres --- */
        .chat-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
        }

        .chat-filters .form-control {
            width: auto;
        }

        .chat-filters input[name="q"] {
            min-width: 280px;
        }

        .chat-filters .checkbox-inline {
            margin: 0 0 0 4px;
            padding-top: 0;
            color: #6b7280;
        }

        /* --- Tableau --- */
        .chat-row {
            cursor: pointer;
        }

        .chat-empty {
            padding: 30px !important;
            text-align: center;
            color: #8a8f98;
        }

        .chat-muted {
            color: #8a8f98;
            font-size: 12px;
        }

        /* --- Badges --- */
        .chat-pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            border: 1px solid #d5d9df;
            color: #6b7280;
            font-size: 12px;
            line-height: 18px;
            white-space: nowrap;
        }

        .chat-pill--on {
            border-color: #2ecc71;
            background: rgba(46, 204, 113, 0.1);
            color: #1e8e4f;
        }

        .chat-pill--danger {
            border-color: #e74c3c;
            background: rgba(231, 76, 60, 0.08);
            color: #b03a2e;
        }

        .chat-pill--muted {
            background: #f4f5f7;
        }
    </style>
@stop

@section('content')
    <div class="page-content container-fluid">
        <div class="panel panel-bordered">
            <div class="panel-body">

                <form method="GET" class="chat-filters">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control"
                           placeholder="Demandeur, e-mail, expert">
                    <select name="expert" id="expert-filter" class="form-control" data-placeholder="Tous les experts">
                        <option value=""></option>
                        @if ($selectedExpert)
                            <option value="{{ $selectedExpert->id }}" selected>{{ $selectedExpert->name }}</option>
                        @endif
                    </select>
                    <label class="checkbox-inline">
                        <input type="checkbox" name="blocked" value="1" @checked(request()->boolean('blocked'))>
                        Avec messages bloqués
                    </label>
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    @if (request()->anyFilled(['q', 'expert', 'blocked']))
                        <a href="{{ route('booking.conversations.index') }}" class="btn btn-link">Réinitialiser</a>
                    @endif
                </form>

                <table class="table table-striped database-tables">
                    <thead>
                        <tr>
                            <th>Demandeur</th>
                            <th>Expert</th>
                            <th class="text-center">Rendez-vous</th>
                            <th class="text-center">Messages</th>
                            <th>État</th>
                            <th>Dernière activité</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($conversations as $row)
                            <tr class="chat-row" data-href="{{ route('booking.conversations.show', $row->id) }}">
                                <td>
                                    <a href="{{ route('booking.conversations.show', $row->id) }}">
                                        {{ trim(($row->user_name ?? '') . ' ' . ($row->user_first_name ?? '')) ?: '—' }}
                                    </a>
                                    <div class="chat-muted">{{ $row->user_email }}</div>
                                </td>
                                <td>{{ $row->expert_name ?? '—' }}</td>
                                <td class="text-center">{{ $row->booking_count }}</td>
                                <td class="text-center">
                                    {{ $row->text_count }}
                                    @if ($row->blocked_attempts > 0)
                                        <div>
                                            <span class="chat-pill chat-pill--danger" title="Messages refusés : téléphone, e-mail ou lien">
                                                {{ $row->blocked_attempts }} bloqué{{ $row->blocked_attempts > 1 ? 's' : '' }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($row->opened_at)
                                        <span class="chat-pill chat-pill--on">Ouverte</span>
                                    @else
                                        <span class="chat-pill chat-pill--muted" title="S'ouvre au premier rendez-vous accepté">En attente d'acceptation</span>
                                    @endif
                                </td>
                                <td>{{ $fmt($row->last_message_at) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="chat-empty">Aucune conversation ne correspond à ces critères.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $conversations->links() }}

            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {
            // Même recherche d'experts que la liste des rendez-vous.
            $('#expert-filter').select2({
                width: '260px',
                allowClear: true,
                placeholder: $('#expert-filter').data('placeholder'),
                language: {
                    noResults: function () { return 'Aucun expert trouvé'; },
                    searching: function () { return 'Recherche…'; },
                    loadingMore: function () { return 'Chargement…'; },
                },
                ajax: {
                    url: '{{ route('booking.appointments.experts-search') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || '', page: params.page || 1 };
                    },
                    processResults: function (data) {
                        return data;
                    },
                },
            });

            $('.chat-row').on('click', function (e) {
                if ($(e.target).closest('a').length) {
                    return;
                }
                window.location = $(this).data('href');
            });
        });
    </script>
@stop
