@extends('voyager::master')

@php
    use App\Http\Controllers\Booking\AppointmentController as Appointments;
    use App\Models\BookingNoShowReport;
    use App\Models\ExpertOfferMode;
    use App\Services\Booking\BookingMeetService as MeetRooms;
    use Carbon\Carbon;

    $tz = config('app.display_timezone', 'Europe/Paris');
    $fmt = fn ($value, $format = 'd/m/Y H:i') => $value ? Carbon::parse($value)->timezone($tz)->format($format) : '—';
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ') . ' €';
    $periods = ['morning' => 'Matin', 'afternoon' => 'Après-midi'];

    [$statusLabel, $statusClass] = Appointments::STATUS[$displayStatus] ?? [$displayStatus, 'muted'];
    $blocksById = $booking->blocks->keyBy('id');

    // Présentiel : pas de salle Meet, la colonne montre le lieu copié à la réservation.
    $onsite    = $booking->mode === ExpertOfferMode::ONSITE;
    $modeLabel = ExpertOfferMode::MODE_LABELS[$booking->mode] ?? $booking->mode;

    // Itinéraire Google Maps : coordonnées si connues, sinon l'adresse en texte.
    $mapsUrl = function (array $l) {
        $params = ['api' => 1];
        if (!empty($l['lat']) && !empty($l['lng'])) {
            $params['destination'] = $l['lat'] . ',' . $l['lng'];
        } else {
            $params['destination'] = trim(($l['address_line'] ?? '') . ', ' . ($l['postal_code'] ?? '') . ' ' . ($l['city'] ?? ''));
        }
        if (!empty($l['google_place_id'])) {
            $params['destination_place_id'] = $l['google_place_id'];
        }
        return 'https://www.google.com/maps/dir/?' . http_build_query($params);
    };

    // Journal : libellé lisible de chaque type d'événement.
    $eventLabels = [
        'submitted'        => 'Demande envoyée',
        'accepted'         => 'Demande acceptée',
        'declined'         => 'Demande refusée',
        'expired'          => 'Demande expirée sans réponse',
        'cancelled'        => 'Rendez-vous annulé',
        'refunded'         => 'Remboursement effectué',
        'noshow_reported'  => 'Absence signalée',
        'noshow_resolved'  => 'Signalement traité',
        'noshow_dismissed' => 'Signalement classé sans suite',
        'refund_synced'    => 'Remboursement constaté sur Stripe',
        'meet_created'     => 'Lien Google Meet créé',
        'meet_cancelled'   => 'Lien Google Meet supprimé',
        'meet_closed'      => 'Salle Google Meet fermée',
        'meet_reopened'    => 'Salle Google Meet rouverte',
        'payment_captured_outside' => 'Débit fait hors application (à vérifier)',
    ];
    $actorLabels = [
        'user'   => 'Demandeur',
        'expert' => 'Expert',
        'admin'  => 'Admin',
        'system' => 'Système',
    ];
@endphp

@section('page_title', 'Rendez-vous #' . $booking->id)

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-calendar"></i> Rendez-vous #{{ $booking->id }}
        <span class="booking-pill booking-pill--{{ $statusClass }}">{{ $statusLabel }}</span>
        <span class="booking-pill booking-pill--{{ $booking->mode }}">{{ $modeLabel }}</span>
    </h1>
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('booking.appointments.index') }}" class="btn btn-default">
        <i class="voyager-angle-left"></i> Retour à la liste
    </a>
@stop

@section('css')
    <style>
        /* --- Salle Meet --- */
        .meet-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px;
        }

        .meet-link {
            font-family: monospace;
            font-size: 13px;
            margin-right: 4px;
        }

        .meet-link--closed {
            color: #9aa0a6;
            text-decoration: line-through;
        }

        .meet-form {
            display: inline;
            margin: 0;
        }

        .meet-btn {
            margin: 0;
            padding: 1px 6px;
        }

        .meet-state {
            margin-top: 4px;
            font-size: 12px;
        }

        .meet-presences > td {
            background: #fafbfc;
            border-top: 0 !important;
            padding: 10px 16px !important;
        }

        .meet-presence-table {
            width: 100%;
        }

        .meet-presence-table td {
            padding: 4px 8px 4px 0;
            vertical-align: top;
            font-size: 13px;
        }

        .meet-person {
            width: 40%;
        }

        .meet-session {
            display: inline-block;
            margin: 0 10px 2px 0;
            font-family: monospace;
        }

        .meet-total {
            width: 90px;
            text-align: right;
            white-space: nowrap;
            font-weight: 600;
        }

        .meet-synced {
            margin-top: 6px;
            font-size: 12px;
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
            font-weight: normal;
            vertical-align: middle;
            white-space: nowrap;
        }

        .booking-pill--on { border-color: #2ecc71; background: rgba(46, 204, 113, .1); color: #1e8e4f; }
        .booking-pill--warn { border-color: #f0ad4e; background: rgba(240, 173, 78, .12); color: #a86500; }
        .booking-pill--danger { border-color: #e74c3c; background: rgba(231, 76, 60, .08); color: #b03a2e; }
        .booking-pill--info { border-color: #5dade2; background: rgba(93, 173, 226, .1); color: #1f618d; }
        .booking-pill--muted { background: #f4f5f7; }
        .booking-pill--online { border-color: #22a7f0; background: rgba(34, 167, 240, .1); color: #136b9c; }
        .booking-pill--onsite { border-color: #9b59b6; background: rgba(155, 89, 182, .1); color: #6c3483; }

        /* --- Lieu (présentiel) --- */
        .booking-place-access {
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
            white-space: pre-line;
        }

        /* --- Structure --- */
        .booking-panel h3 {
            margin: 0 0 14px;
            font-size: 16px;
            font-weight: 600;
        }

        .booking-muted {
            color: #8a8f98;
        }

        .booking-dl {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 8px 14px;
            margin: 0;
        }

        .booking-dl dt {
            color: #8a8f98;
            font-weight: normal;
        }

        .booking-dl dd {
            margin: 0;
        }

        .booking-message {
            padding: 12px 14px;
            border-radius: 8px;
            background: #f7f9fc;
            white-space: pre-line;
        }

        /* --- Montants --- */
        .booking-amounts td {
            padding: 5px 0 !important;
            border: none !important;
        }

        .booking-amounts .is-total td {
            border-top: 1px solid #eef0f3 !important;
            padding-top: 9px !important;
            font-weight: 600;
        }

        /* --- Actions --- */
        .booking-action + .booking-action {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #eef0f3;
        }

        .booking-action h4 {
            margin: 0 0 6px;
            font-size: 14px;
            font-weight: 600;
        }

        .booking-action p.booking-muted {
            margin: 0 0 10px;
            font-size: 12px;
        }

        .booking-party {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }

        .booking-party label {
            flex: 1;
            margin: 0;
            padding: 8px 10px;
            border: 1px solid #d5d9df;
            border-radius: 8px;
            font-weight: normal;
            cursor: pointer;
            text-align: center;
        }

        .booking-party input {
            display: none;
        }

        .booking-party input:checked + span {
            font-weight: 600;
            color: #1d3557;
        }

        .booking-party label:has(input:checked) {
            border-color: #1d3557;
            background: #f4f7fb;
        }

        .booking-rule {
            margin: 4px 0 10px;
            font-size: 12px;
            color: #8a8f98;
        }

        /* --- Signalements --- */
        .booking-report {
            padding: 14px;
            border: 1px solid #f0ad4e;
            border-radius: 10px;
            background: rgba(240, 173, 78, .06);
        }

        .booking-report + .booking-report {
            margin-top: 12px;
        }

        .booking-report.is-closed {
            border-color: #e2e8f0;
            background: #fafbfc;
        }

        .booking-report-forms {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-top: 12px;
        }

        /* --- Journal --- */
        .booking-timeline {
            list-style: none;
            margin: 0;
            padding: 0 0 0 18px;
            border-left: 2px solid #eef0f3;
        }

        .booking-timeline li {
            position: relative;
            padding: 0 0 16px 10px;
        }

        .booking-timeline li::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 4px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #1d3557;
        }

        .booking-timeline li.is-admin::before {
            border-color: #e67e22;
            background: #e67e22;
        }

        .booking-timeline .when {
            font-size: 12px;
            color: #8a8f98;
        }

        .booking-timeline .detail {
            font-size: 12px;
            color: #6b7280;
        }
    </style>
@stop

@section('content')
    <div class="page-content container-fluid">

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="list-unstyled" style="margin: 0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row">
            {{-- ===================== Colonne principale ===================== --}}
            <div class="col-md-8">

                {{-- --- Rendez-vous --- --}}
                <div class="panel panel-bordered booking-panel">
                    <div class="panel-body">
                        <h3>{{ $booking->offer_title }}</h3>
                        <dl class="booking-dl">
                            <dt>Expert</dt>
                            <dd>
                                @if ($booking->expert)
                                    <a href="{{ route('booking.experts.show', $booking->expert_id) }}">{{ $booking->expert->name }}</a>
                                @else
                                    —
                                @endif
                            </dd>
                            <dt>Type</dt>
                            <dd><span class="booking-pill booking-pill--{{ $booking->mode }}">{{ $modeLabel }}</span></dd>
                            <dt>Demandeur</dt>
                            <dd>
                                {{ $requester }}
                                @if ($booking->user)
                                    <span class="booking-muted">· {{ $booking->user->email }}</span>
                                @endif
                            </dd>
                            <dt>Demande envoyée</dt>
                            <dd>{{ $fmt($booking->requested_at) }}</dd>
                            @if ($booking->status === 'pending')
                                <dt>Réponse attendue avant</dt>
                                <dd><strong>{{ $fmt($booking->respond_by) }}</strong></dd>
                            @elseif ($booking->responded_at)
                                <dt>Réponse de l'expert</dt>
                                <dd>{{ $fmt($booking->responded_at) }}</dd>
                            @endif
                            @if ($booking->status === 'declined')
                                <dt>Motif du refus</dt>
                                <dd>
                                    {{ $booking->declineReason?->label ?? '—' }}
                                    @if ($booking->decline_note)
                                        <div class="booking-muted">« {{ $booking->decline_note }} »</div>
                                    @endif
                                </dd>
                            @endif
                            @if ($booking->status === 'cancelled')
                                <dt>Annulation</dt>
                                <dd>
                                    {{ $fmt($booking->cancelled_at) }} · au nom de
                                    {{ Appointments::PARTY_LABELS[$booking->cancelled_by] ?? '—' }}
                                    @if ($booking->cancelled_by_admin_id)
                                        <span class="booking-muted">(par l'administration)</span>
                                    @endif
                                    @if ($booking->cancel_reason)
                                        <div class="booking-muted">« {{ $booking->cancel_reason }} »</div>
                                    @endif
                                </dd>
                            @endif
                        </dl>

                        <h3 style="margin-top: 24px;">Demi-journées</h3>
                        <table class="table table-striped" style="margin: 0;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Période</th>
                                    <th>Horaires (expert)</th>
                                    <th>{{ $onsite ? 'Lieu' : 'Visio' }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($booking->blocks as $block)
                                    @php
                                        $meetPresences = MeetRooms::summary($block);
                                        $meetEnded     = $block->end_at && $block->end_at->isPast();
                                        $meetPost      = fn ($name) => route('booking.appointments.meet' . ($name ? '.' . $name : ''), [$booking->id, $block->id]);
                                    @endphp
                                    <tr>
                                        <td>{{ $block->date->format('d/m/Y') }}</td>
                                        <td>{{ $periods[$block->period] ?? $block->period }}</td>
                                        <td>{{ $block->start_local }} – {{ $block->end_local }}</td>
                                        <td>
                                            @if ($onsite)
                                                @if ($block->location)
                                                    <strong>{{ $block->location['label'] ?? '' }}</strong>
                                                    <div>
                                                        {{ $block->location['address_line'] ?? '' }},
                                                        {{ trim(($block->location['postal_code'] ?? '') . ' ' . ($block->location['city'] ?? '')) }}
                                                    </div>
                                                    @if (!empty($block->location['access_details']))
                                                        <div class="booking-place-access">{{ $block->location['access_details'] }}</div>
                                                    @endif
                                                    <a href="{{ $mapsUrl($block->location) }}" target="_blank" rel="noopener" class="booking-muted">
                                                        <i class="voyager-location"></i> Itinéraire
                                                    </a>
                                                @else
                                                    <span class="booking-muted">Lieu non enregistré</span>
                                                @endif
                                            @elseif ($block->meet_url)
                                                <div class="meet-row">
                                                    <a href="{{ $block->meet_url }}" target="_blank" rel="noopener"
                                                       class="meet-link {{ $block->meet_closed_at ? 'meet-link--closed' : '' }}">
                                                        {{ \Illuminate\Support\Str::after($block->meet_url, 'https://') }}
                                                    </a>
                                                    <button type="button" class="btn btn-xs btn-default meet-btn meet-copy" data-url="{{ $block->meet_url }}"
                                                            title="Copier le lien" aria-label="Copier le lien">
                                                        <i class="voyager-documentation"></i>
                                                    </button>
                                                    <form method="POST" class="meet-form" action="{{ $meetPost('sync') }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-xs btn-default meet-btn"
                                                                title="Rafraîchir les présences" aria-label="Rafraîchir les présences">
                                                            <i class="voyager-refresh"></i>
                                                        </button>
                                                    </form>
                                                    @if ($block->meet_closed_at)
                                                        <form method="POST" class="meet-form" action="{{ $meetPost('reopen') }}"
                                                              onsubmit="return confirm('Rouvrir la salle ? Le même lien fonctionnera de nouveau pendant {{ MeetRooms::REOPEN_HOURS }} h.');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-xs btn-success meet-btn"
                                                                    title="Rouvrir la salle" aria-label="Rouvrir la salle">
                                                                <i class="voyager-key"></i>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <form method="POST" class="meet-form" action="{{ $meetPost('close') }}"
                                                              onsubmit="return confirm('Fermer la salle maintenant ? Les participants seront déconnectés et le lien ne fonctionnera plus.');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-xs btn-warning meet-btn"
                                                                    title="Fermer la salle" aria-label="Fermer la salle">
                                                                <i class="voyager-lock"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    {{-- Après la fin : on ferme, on ne supprime plus (les présences restent). --}}
                                                    @unless ($meetEnded)
                                                        <form method="POST" class="meet-form" action="{{ $meetPost('cancel') }}"
                                                              onsubmit="return confirm('Supprimer ce lien ? La salle sera fermée et le lien ne fonctionnera plus.');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-xs btn-danger meet-btn"
                                                                    title="Supprimer le lien" aria-label="Supprimer le lien">
                                                                <i class="voyager-trash"></i>
                                                            </button>
                                                        </form>
                                                    @endunless
                                                </div>

                                                <div class="meet-state booking-muted">
                                                    @if ($block->meet_closed_at)
                                                        Salle fermée le {{ $fmt($block->meet_closed_at) }}
                                                        ({{ MeetRooms::REASON_LABELS[$block->meet_close_reason] ?? $block->meet_close_reason }})
                                                    @elseif ($block->meet_reopened_until && $block->meet_reopened_until->isFuture())
                                                        Rouverte jusqu'à {{ $fmt($block->meet_reopened_until, 'H:i') }}
                                                    @else
                                                        Salle ouverte
                                                    @endif
                                                    · <a href="#" class="meet-toggle" data-target="#meet-presences-{{ $block->id }}">
                                                        Présences ({{ count($meetPresences) }})
                                                    </a>
                                                </div>
                                            @else
                                                <form method="POST" class="meet-form" action="{{ $meetPost(null) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-primary" style="margin: 0;"
                                                            onclick="this.disabled = true; this.form.submit();">
                                                        <i class="voyager-video"></i> Créer le lien
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>

                                    @if (!$onsite && $block->meet_url)
                                        {{-- Présences relevées chez Google, repliées par défaut. --}}
                                        <tr id="meet-presences-{{ $block->id }}" class="meet-presences" style="display: none;">
                                            <td colspan="4">
                                                @if (!$block->meet_synced_at)
                                                    <span class="booking-muted">Pas encore relevé : cliquez sur <i class="voyager-refresh"></i> pour relever les présences.</span>
                                                @elseif (!$meetPresences)
                                                    <span class="booking-muted">Personne ne s'est connecté.</span>
                                                @else
                                                    <table class="meet-presence-table">
                                                        @foreach ($meetPresences as $person)
                                                            <tr>
                                                                <td class="meet-person">
                                                                    <strong>{{ $person['name'] }}</strong>
                                                                    <span class="booking-pill">{{ ['google' => 'Compte Google', 'anonymous' => 'Anonyme', 'phone' => 'Téléphone'][$person['kind']] ?? $person['kind'] }}</span>
                                                                </td>
                                                                <td>
                                                                    @foreach ($person['sessions'] as $session)
                                                                        <span class="meet-session">
                                                                            {{ $fmt($session->joined_at, 'H:i') }} → {{ $session->left_at ? $fmt($session->left_at, 'H:i') : 'en cours' }}
                                                                        </span>
                                                                    @endforeach
                                                                </td>
                                                                <td class="meet-total">
                                                                    {{ intdiv($person['seconds'], 3600) ? intdiv($person['seconds'], 3600) . ' h ' : '' }}{{ str_pad(intdiv($person['seconds'] % 3600, 60), 2, '0', STR_PAD_LEFT) }} min
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </table>
                                                @endif
                                                @if ($block->meet_synced_at)
                                                    <div class="booking-muted meet-synced">
                                                        Relevé le {{ $fmt($block->meet_synced_at) }} · heures de Paris ({{ $tz }})
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>

                        @if ($booking->message)
                            <h3 style="margin-top: 24px;">Message du demandeur</h3>
                            <div class="booking-message">{{ $booking->message }}</div>
                        @endif
                    </div>
                </div>

                {{-- --- Signalements d'absence --- --}}
                @if ($booking->noShowReports->isNotEmpty())
                    <div class="panel panel-bordered booking-panel">
                        <div class="panel-body">
                            <h3>Signalements d'absence</h3>

                            @foreach ($booking->noShowReports as $report)
                                @php
                                    $open = $report->status === BookingNoShowReport::STATUS_OPEN;
                                    $suggestion = $noShowSuggest[$report->id] ?? ['amount' => 0, 'rule' => ''];
                                @endphp
                                <div class="booking-report {{ $open ? '' : 'is-closed' }}">
                                    <strong>
                                        {{ $report->reporter === 'user' ? "Le demandeur signale l'absence de l'expert" : "L'expert signale l'absence du demandeur" }}
                                    </strong>
                                    <span class="booking-muted">· {{ $fmt($report->created_at) }}</span>
                                    <div style="margin-top: 6px;">
                                        Demi-journée(s) :
                                        @foreach ($report->block_ids as $blockId)
                                            @php $b = $blocksById->get($blockId); @endphp
                                            @if ($b)
                                                <span class="booking-pill">{{ $b->date->format('d/m') }} {{ $periods[$b->period] ?? '' }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                    @if ($report->note)
                                        <div class="booking-message" style="margin-top: 8px;">{{ $report->note }}</div>
                                    @endif

                                    @if ($open)
                                        <div class="booking-report-forms">
                                            <form method="POST" action="{{ route('booking.appointments.noshow.resolve', [$booking->id, $report->id]) }}"
                                                  onsubmit="return confirm('Confirmer le traitement et le remboursement indiqué ?');">
                                                @csrf
                                                <label>Remboursement au demandeur (€)</label>
                                                <input type="number" name="refund" step="0.01" min="0" max="{{ $refundable }}"
                                                       value="{{ number_format($suggestion['amount'], 2, '.', '') }}" class="form-control">
                                                <div class="booking-rule">{{ $suggestion['rule'] }} Maximum : {{ $money($refundable) }}.</div>
                                                <textarea name="note" rows="2" class="form-control" maxlength="500"
                                                          placeholder="Note interne (facultatif)"></textarea>
                                                <button type="submit" class="btn btn-warning" style="margin-top: 8px;">Traiter et rembourser</button>
                                            </form>
                                            <form method="POST" action="{{ route('booking.appointments.noshow.dismiss', [$booking->id, $report->id]) }}"
                                                  onsubmit="return confirm('Classer ce signalement sans suite ?');">
                                                @csrf
                                                <label>Classer sans suite</label>
                                                <textarea name="note" rows="4" class="form-control" maxlength="500"
                                                          placeholder="Pourquoi ? (facultatif)"></textarea>
                                                <button type="submit" class="btn btn-default" style="margin-top: 8px;">Classer sans suite</button>
                                            </form>
                                        </div>
                                    @else
                                        <div class="booking-muted" style="margin-top: 8px;">
                                            {{ $report->status === 'resolved' ? 'Traité' : 'Classé sans suite' }} le {{ $fmt($report->resolved_at) }}
                                            @if ((float) $report->refund_amount > 0)
                                                · remboursé {{ $money($report->refund_amount) }}
                                            @endif
                                            @if ($report->resolution_note)
                                                · « {{ $report->resolution_note }} »
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- --- Journal --- --}}
                <div class="panel panel-bordered booking-panel">
                    <div class="panel-body">
                        <h3>Journal</h3>
                        @if ($booking->events->isEmpty())
                            <p class="booking-muted">Aucun événement enregistré.</p>
                        @else
                            <ul class="booking-timeline">
                                @foreach ($booking->events as $event)
                                    @php $data = $event->data ?? []; @endphp
                                    <li class="{{ $event->actor === 'admin' ? 'is-admin' : '' }}">
                                        <strong>{{ $eventLabels[$event->type] ?? $event->type }}</strong>
                                        <span class="booking-muted">
                                            · {{ $actorLabels[$event->actor] ?? $event->actor }}
                                            @if ($event->admin)
                                                ({{ $event->admin->name }})
                                            @endif
                                            @if ($event->on_behalf_of)
                                                au nom de {{ Appointments::PARTY_LABELS[$event->on_behalf_of] ?? $event->on_behalf_of }}
                                            @endif
                                        </span>
                                        <div class="when">{{ $fmt($event->created_at) }}</div>
                                        @php
                                            $details = array_filter([
                                                isset($data['reason']) && $event->type !== 'refunded' ? 'Motif : ' . $data['reason'] : null,
                                                isset($data['note']) ? '« ' . $data['note'] . ' »' : null,
                                                !empty($data['refund']) ? 'Remboursement : ' . $money($data['refund']) : null,
                                                $event->type === 'refunded' && isset($data['amount']) ? $money($data['amount']) . ' (Stripe ' . ($data['refund_id'] ?? '') . ')' : null,
                                                $event->type === 'accepted' && !empty($data['captured']) ? 'Débité : ' . $money($data['captured']) : null,
                                                $event->type === 'refund_synced' && isset($data['amount']) ? $money($data['amount']) . ' (total remboursé : ' . $money($data['total'] ?? 0) . ')' : null,
                                                $event->type === 'payment_captured_outside' && isset($data['amount']) ? 'Débité : ' . $money($data['amount']) : null,
                                                ($data['source'] ?? null) === 'webhook' ? 'via webhook Stripe' : null,
                                                $event->type === 'meet_reopened' && isset($data['until']) ? "Jusqu'à " . $fmt($data['until'], 'H:i') : null,
                                                in_array($event->type, ['meet_created', 'meet_cancelled', 'meet_closed', 'meet_reopened'], true) && isset($data['date']) ? \Carbon\Carbon::parse($data['date'])->format('d/m/Y') . ' · ' . ($periods[$data['period'] ?? ''] ?? '') : null,
                                            ]);
                                        @endphp
                                        @if ($details)
                                            <div class="detail">{{ implode(' · ', $details) }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ===================== Colonne latérale ===================== --}}
            <div class="col-md-4">

                {{-- --- Messagerie du couple (tous rendez-vous confondus) --- --}}
                @php
                    $conversation = \App\Models\BookingConversation::where('expert_id', $booking->expert_id)
                        ->where('user_id', $booking->user_id)
                        ->withCount(['messages as text_count' => fn ($q) => $q->where('kind', 'text')])
                        ->first();
                @endphp
                @if ($conversation)
                    <div class="panel panel-bordered booking-panel">
                        <div class="panel-body">
                            <h3>Messagerie</h3>
                            <p class="booking-muted" style="margin-bottom: 10px;">
                                {{ $conversation->text_count }} message{{ $conversation->text_count > 1 ? 's' : '' }}
                                @if ($conversation->blocked_attempts > 0)
                                    · <span class="booking-pill booking-pill--danger">{{ $conversation->blocked_attempts }} bloqué{{ $conversation->blocked_attempts > 1 ? 's' : '' }}</span>
                                @endif
                                @unless ($conversation->opened_at)
                                    · ouverte au premier rendez-vous accepté
                                @endunless
                            </p>
                            <a href="{{ route('booking.conversations.show', $conversation->id) }}" class="btn btn-default btn-block" target="_blank">
                                <i class="voyager-chat"></i> Voir la conversation
                            </a>
                        </div>
                    </div>
                @endif

                {{-- --- Actions --- --}}
                @if ($booking->status === 'pending' || $canCancel)
                    <div class="panel panel-bordered booking-panel">
                        <div class="panel-body">
                            <h3>Actions</h3>

                            @if ($booking->status === 'pending')
                                <div class="booking-action">
                                    <h4>Répondre au nom de l'expert</h4>
                                    <p class="booking-muted">À la demande de l'expert, par exemple s'il n'a pas accès à l'application.</p>
                                    <form method="POST" action="{{ route('booking.appointments.accept', $booking->id) }}"
                                          onsubmit="return confirm('Accepter au nom de l\'expert ? Le montant réservé sera débité.');"
                                          style="margin-bottom: 10px;">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-block">Accepter la demande</button>
                                    </form>
                                    <form method="POST" action="{{ route('booking.appointments.decline', $booking->id) }}"
                                          onsubmit="return confirm('Refuser au nom de l\'expert ? L\'empreinte bancaire sera libérée.');">
                                        @csrf
                                        <select name="reason_id" class="form-control" required>
                                            <option value="">Motif du refus…</option>
                                            @foreach ($reasons as $reason)
                                                <option value="{{ $reason->id }}" @selected(old('reason_id') == $reason->id)>{{ $reason->label }}</option>
                                            @endforeach
                                        </select>
                                        <textarea name="note" rows="2" class="form-control" maxlength="300" style="margin-top: 8px;"
                                                  placeholder="Mot pour le demandeur (facultatif)">{{ old('note') }}</textarea>
                                        <button type="submit" class="btn btn-danger btn-block" style="margin-top: 8px;">Refuser la demande</button>
                                    </form>
                                </div>
                            @endif

                            @if ($canCancel)
                                <div class="booking-action">
                                    <h4>Annuler le rendez-vous</h4>
                                    <p class="booking-muted">
                                        @if ($booking->status === 'pending')
                                            Rien n'a encore été débité : l'empreinte bancaire est libérée.
                                        @else
                                            Le montant proposé suit la politique d'annulation ; il reste modifiable.
                                        @endif
                                    </p>
                                    <form method="POST" action="{{ route('booking.appointments.cancel', $booking->id) }}" id="cancel-form"
                                          onsubmit="return confirm('Annuler ce rendez-vous ? Cette action est définitive.');">
                                        @csrf
                                        <div class="booking-party">
                                            @foreach (['user' => 'Au nom du demandeur', 'expert' => "Au nom de l'expert"] as $party => $label)
                                                <label>
                                                    <input type="radio" name="party" value="{{ $party }}"
                                                           data-refund="{{ number_format($suggest[$party]['amount'], 2, '.', '') }}"
                                                           data-rule="{{ $suggest[$party]['rule'] }}"
                                                           @checked(old('party', 'user') === $party)>
                                                    <span>{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>

                                        @if ($refundable > 0)
                                            <label for="cancel-refund">Remboursement au demandeur (€)</label>
                                            <input type="number" id="cancel-refund" name="refund" step="0.01" min="0" max="{{ $refundable }}"
                                                   value="{{ old('refund', number_format($suggest[old('party', 'user')]['amount'], 2, '.', '')) }}"
                                                   class="form-control">
                                            <div class="booking-rule" id="cancel-rule">{{ $suggest[old('party', 'user')]['rule'] }}</div>
                                            <div class="booking-rule">Maximum : {{ $money($refundable) }}.</div>
                                        @endif

                                        <textarea name="reason" rows="3" class="form-control" maxlength="500" required
                                                  placeholder="Motif (appel du demandeur, empêchement de l'expert…)">{{ old('reason') }}</textarea>
                                        <button type="submit" class="btn btn-danger btn-block" style="margin-top: 8px;">Annuler le rendez-vous</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- --- Paiement --- --}}
                <div class="panel panel-bordered booking-panel">
                    <div class="panel-body">
                        <h3>Paiement</h3>
                        @if ($booking->payment_required)
                            <table class="table booking-amounts" style="margin: 0;">
                                <tr><td>Prix HT</td><td class="text-right">{{ $money($booking->amount_ht) }}</td></tr>
                                <tr><td>TVA ({{ rtrim(rtrim(number_format($booking->tva_rate, 2, ',', ''), '0'), ',') }} %)</td><td class="text-right">{{ $money($booking->tva_amount) }}</td></tr>
                                <tr class="is-total"><td>Total TTC</td><td class="text-right">{{ $money($booking->amount_ttc) }}</td></tr>
                                @if ((float) $booking->refunded_amount > 0)
                                    <tr><td>Remboursé</td><td class="text-right">− {{ $money($booking->refunded_amount) }}</td></tr>
                                    <tr class="is-total"><td>Conservé</td><td class="text-right">{{ $money($kept) }}</td></tr>
                                @endif
                                <tr>
                                    <td>Reversement expert estimé (HT)</td>
                                    <td class="text-right">{{ $money($payoutHt) }}</td>
                                </tr>
                            </table>
                            <p class="booking-muted" style="margin: 8px 0 0; font-size: 12px;">
                                Commission {{ rtrim(rtrim(number_format($booking->commission_rate, 2, ',', ''), '0'), ',') }} %.
                                @if ($booking->status === 'pending')
                                    Montant réservé sur la carte, pas encore débité.
                                @elseif (in_array($booking->status, ['declined', 'expired'], true) || ($booking->status === 'cancelled' && !$booking->responded_at))
                                    Empreinte libérée : rien n'a été débité.
                                @endif
                            </p>
                            @if ($booking->invoice)
                                <p class="booking-muted" style="margin: 8px 0 0; font-size: 12px;">
                                    Facture #{{ $booking->invoice->id }} · {{ $booking->invoice->status }}
                                    @if ($booking->invoice->stripe_payment_intent_id)
                                        · {{ $booking->invoice->stripe_payment_intent_id }}
                                    @endif
                                </p>
                            @endif
                        @else
                            <p class="booking-muted" style="margin: 0;">Rendez-vous offert : aucun paiement.</p>
                        @endif
                    </div>
                </div>

                {{-- --- Facturation --- --}}
                <div class="panel panel-bordered booking-panel">
                    <div class="panel-body">
                        <h3>Facturation</h3>
                        <div class="booking-message">{{ $booking->client_address }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {
            // Présences d'une demi-journée : ouvrir / replier.
            $('.meet-toggle').on('click', function (e) {
                e.preventDefault();
                $($(this).data('target')).toggle();
            });

            // Copie du lien Meet dans le presse-papiers.
            $('.meet-copy').on('click', function () {
                var url = $(this).data('url');
                navigator.clipboard.writeText(url).then(function () {
                    toastr.success('Lien copié');
                });
            });

            // Le remboursement proposé suit la partie choisie ; l'admin peut ensuite le modifier.
            $('#cancel-form input[name="party"]').on('change', function () {
                $('#cancel-refund').val($(this).data('refund'));
                $('#cancel-rule').text($(this).data('rule'));
            });
        });
    </script>
@stop