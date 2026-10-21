@extends('voyager::master')

@php
    use App\Http\Controllers\Booking\AppointmentController as Appointments;
    use Carbon\Carbon;

    $tz = config('app.display_timezone', 'Europe/Paris');
    $fmt = fn ($value, $format = 'd/m/Y H:i') => $value ? Carbon::parse($value)->timezone($tz)->format($format) : '—';
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ') . ' €';

    // État affiché : un rendez-vous accepté dont la dernière demi-journée est passée est « terminé ».
    $statusOf = function ($row) {
        if ($row->status === 'accepted' && $row->last_end && Carbon::parse($row->last_end)->isPast()) {
            return 'completed';
        }
        return $row->status;
    };
@endphp

@section('page_title', 'Rendez-vous')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-calendar"></i> Rendez-vous
    </h1>
    <a href="{{ route('booking.experts.index') }}" class="btn btn-default">
        <i class="voyager-people"></i> Experts
    </a>
    <a href="{{ route('booking.config.index') }}" class="btn btn-default">
        <i class="voyager-settings"></i> Configuration générale
    </a>
@stop

@section('css')
    <style>
        /* --- Onglets --- */
        .stats-tabs {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 18px;
            padding: 4px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
        }

        .stats-tab {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 18px;
            border-radius: 9px;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.2;
            text-decoration: none !important;
            transition: background .15s, color .15s;
        }

        .stats-tab:hover {
            color: #1d3557;
            background: #f4f7fb;
        }

        .stats-tab.is-active,
        .stats-tab.is-active:hover {
            background: #1d3557;
            color: #fff;
            box-shadow: 0 2px 6px rgba(29, 53, 87, .28);
        }

        .stats-tab-count {
            padding: 1px 8px;
            border-radius: 10px;
            background: rgba(0, 0, 0, .07);
            font-size: 11px;
        }

        .stats-tab.is-active .stats-tab-count {
            background: rgba(255, 255, 255, .22);
        }

        /* Onglet à traiter : compteur rouge tant qu'il reste quelque chose. */
        .stats-tab-count--alert {
            background: #e74c3c !important;
            color: #fff;
        }

        /* --- Filtres --- */
        .booking-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
        }

        .booking-filters .form-control {
            width: auto;
        }

        .booking-filters input[name="q"] {
            min-width: 280px;
        }

        .booking-filters label {
            margin: 0;
            color: #8a8f98;
            font-weight: normal;
        }

        /* --- Tableau --- */
        .booking-row {
            cursor: pointer;
        }

        .booking-empty {
            padding: 30px !important;
            text-align: center;
            color: #8a8f98;
        }

        .booking-muted {
            color: #8a8f98;
            font-size: 12px;
        }

        .booking-deadline--urgent {
            color: #c0392b;
            font-weight: 600;
        }

        /* --- Badges --- */
        .booking-pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            border: 1px solid #d5d9df;
            color: #6b7280;
            font-size: 12px;
            line-height: 18px;
            white-space: nowrap;
        }

        .booking-pill--on {
            border-color: #2ecc71;
            background: rgba(46, 204, 113, 0.1);
            color: #1e8e4f;
        }

        .booking-pill--warn {
            border-color: #f0ad4e;
            background: rgba(240, 173, 78, 0.12);
            color: #a86500;
        }

        .booking-pill--danger {
            border-color: #e74c3c;
            background: rgba(231, 76, 60, 0.08);
            color: #b03a2e;
        }

        .booking-pill--info {
            border-color: #5dade2;
            background: rgba(93, 173, 226, 0.1);
            color: #1f618d;
        }

        .booking-pill--muted {
            background: #f4f5f7;
        }
    </style>
@stop

@section('content')
    <div class="page-content container-fluid">

        <div class="stats-tabs">
            @foreach (Appointments::TABS as $key => [$label, $icon])
                <a href="{{ route('booking.appointments.index', array_merge(request()->except(['tab', 'page']), ['tab' => $key])) }}"
                   class="stats-tab {{ $tab === $key ? 'is-active' : '' }}">
                    <i class="{{ $icon }}"></i> {{ $label }}
                    <span class="stats-tab-count {{ in_array($key, ['pending', 'noshow'], true) && $counts[$key] > 0 ? 'stats-tab-count--alert' : '' }}">
                        {{ $counts[$key] }}
                    </span>
                </a>
            @endforeach
        </div>

        <div class="panel panel-bordered">
            <div class="panel-body">

                <form method="GET" class="booking-filters">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control"
                           placeholder="N°, demandeur, e-mail, expert, thème">
                    {{-- Recherche à la frappe : seule l'option sélectionnée est rendue côté serveur. --}}
                    <select name="expert" id="expert-filter" class="form-control" data-placeholder="Tous les experts">
                        <option value=""></option>
                        @if ($selectedExpert)
                            <option value="{{ $selectedExpert->id }}" selected>{{ $selectedExpert->name }}</option>
                        @endif
                    </select>
                    <label for="from">Séances du</label>
                    <input type="date" id="from" name="from" value="{{ request('from') }}" class="form-control">
                    <label for="to">au</label>
                    <input type="date" id="to" name="to" value="{{ request('to') }}" class="form-control">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    @if (request()->anyFilled(['q', 'expert', 'from', 'to']))
                        <a href="{{ route('booking.appointments.index', ['tab' => $tab]) }}" class="btn btn-link">Réinitialiser</a>
                    @endif
                </form>

                <table class="table table-striped database-tables">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Demandeur</th>
                            <th>Expert</th>
                            <th>Thème</th>
                            <th>Séances</th>
                            <th>État</th>
                            <th class="text-right">Montant TTC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bookings as $row)
                            @php
                                $status = $statusOf($row);
                                [$statusLabel, $statusClass] = Appointments::STATUS[$status] ?? [$status, 'muted'];
                                $deadline = $row->respond_by ? Carbon::parse($row->respond_by) : null;
                                $urgent = $status === 'pending' && $deadline && $deadline->diffInHours(now(), false) > -24;
                            @endphp
                            <tr class="booking-row" data-href="{{ route('booking.appointments.show', $row->id) }}">
                                <td>
                                    <a href="{{ route('booking.appointments.show', $row->id) }}">#{{ $row->id }}</a>
                                </td>
                                <td>
                                    {{ $row->user_name ?? '—' }}
                                    <div class="booking-muted">{{ $row->user_email }}</div>
                                </td>
                                <td>{{ $row->expert_name ?? '—' }}</td>
                                <td>
                                    {{ $row->offer_title }}
                                    <div class="booking-muted">
                                        {{ $row->blocks_count }} demi-journée{{ $row->blocks_count > 1 ? 's' : '' }}
                                    </div>
                                </td>
                                <td>
                                    {{ $fmt($row->first_start, 'd/m/Y') }}
                                    @if ($row->blocks_count > 1)
                                        <div class="booking-muted">→ {{ $fmt($row->last_end, 'd/m/Y') }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="booking-pill booking-pill--{{ $statusClass }}">{{ $statusLabel }}</span>
                                    @if ($row->open_reports > 0)
                                        <span class="booking-pill booking-pill--danger" title="Signalement d'absence à traiter">Absence</span>
                                    @endif
                                    @if ($status === 'pending' && $deadline)
                                        <div class="booking-muted {{ $urgent ? 'booking-deadline--urgent' : '' }}">
                                            Réponse avant {{ $fmt($deadline) }}
                                        </div>
                                    @elseif ($status === 'cancelled' && $row->cancelled_by)
                                        <div class="booking-muted">par {{ Appointments::PARTY_LABELS[$row->cancelled_by] ?? $row->cancelled_by }}</div>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($row->payment_required)
                                        {{ $money($row->amount_ttc) }}
                                        @if ((float) $row->refunded_amount > 0)
                                            <div class="booking-muted">remboursé {{ $money($row->refunded_amount) }}</div>
                                        @endif
                                    @else
                                        <span class="booking-muted">Offert</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="booking-empty">Aucun rendez-vous ne correspond à ces critères.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $bookings->links() }}

            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {
            // Filtre expert : recherche serveur à la frappe, 20 résultats par page.
            $('#expert-filter').select2({
                width: '260px',
                allowClear: true,
                placeholder: $('#expert-filter').data('placeholder'),
                minimumInputLength: 0,
                language: {
                    inputTooShort: function () { return 'Tapez le nom d\'un expert'; },
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

            // Toute la ligne est cliquable ; le lien du numéro reste utilisable (nouvel onglet).
            $('.booking-row').on('click', function (e) {
                if ($(e.target).closest('a').length) {
                    return;
                }
                window.location = $(this).data('href');
            });
        });
    </script>
@stop