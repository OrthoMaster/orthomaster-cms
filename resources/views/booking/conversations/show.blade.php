@extends('voyager::master')

@php
    use App\Http\Controllers\Booking\AppointmentController as Appointments;
    use App\Models\ExpertOfferMode;
    use Carbon\Carbon;

    $tz = config('app.display_timezone', 'Europe/Paris');
    $fmt = fn ($value, $format = 'd/m/Y H:i') => $value ? Carbon::parse($value)->timezone($tz)->format($format) : '—';

    $senderNames = [
        'user'   => $requester,
        'expert' => $expertName,
    ];

    // Lu par l'autre partie : le dernier message vu de chaque côté.
    $readBy = [
        'user'   => $conversation->expert_last_read_id,
        'expert' => $conversation->user_last_read_id,
    ];
@endphp

@section('page_title', 'Messagerie · ' . $requester . ' / ' . $expertName)

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-chat"></i> {{ $requester }} <span class="chat-muted-title">↔</span> {{ $expertName }}
        @if ($conversation->opened_at)
            <span class="chat-pill chat-pill--on">Ouverte</span>
        @else
            <span class="chat-pill chat-pill--muted">En attente d'acceptation</span>
        @endif
    </h1>
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('booking.conversations.index') }}" class="btn btn-default">
        <i class="voyager-angle-left"></i> Retour
    </a>
@stop

@section('css')
    <style>
        /* --- Fil --- */
        .chat-thread {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 6px 4px;
        }

        .chat-day {
            align-self: center;
            margin: 14px 0 6px;
            padding: 2px 12px;
            border-radius: 999px;
            background: #f1f4f8;
            color: #6b7280;
            font-size: 12px;
            font-weight: 600;
        }

        .chat-msg {
            max-width: 72%;
            display: flex;
            flex-direction: column;
        }

        .chat-msg--user {
            align-self: flex-start;
        }

        .chat-msg--expert {
            align-self: flex-end;
            align-items: flex-end;
        }

        .chat-msg .who {
            margin: 0 4px 2px;
            color: #8a8f98;
            font-size: 11px;
        }

        .chat-bubble {
            padding: 9px 13px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.45;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .chat-msg--user .chat-bubble {
            background: #eef2f7;
            color: #1f2937;
            border-bottom-left-radius: 4px;
        }

        .chat-msg--expert .chat-bubble {
            background: #1d3557;
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .chat-msg .meta {
            margin: 2px 4px 0;
            color: #a0a6b0;
            font-size: 11px;
        }

        /* --- Événements des rendez-vous --- */
        .chat-event {
            align-self: center;
            max-width: 80%;
            margin: 6px 0;
            padding: 7px 14px;
            border: 1px dashed #d5d9df;
            border-radius: 12px;
            background: #fafbfc;
            color: #4b5563;
            font-size: 12.5px;
            text-align: center;
        }

        .chat-event a {
            font-weight: 600;
        }

        .chat-event .meta {
            display: block;
            margin-top: 2px;
            color: #a0a6b0;
            font-size: 11px;
        }

        .chat-older {
            text-align: center;
            margin-bottom: 8px;
        }

        .chat-empty {
            padding: 30px;
            text-align: center;
            color: #8a8f98;
        }

        /* --- Colonne latérale --- */
        .chat-side h3 {
            margin-top: 0;
            font-size: 16px;
        }

        .chat-side dl {
            margin: 0;
        }

        .chat-side dt {
            color: #8a8f98;
            font-size: 12px;
            font-weight: normal;
        }

        .chat-side dd {
            margin-bottom: 10px;
        }

        .chat-booking {
            display: block;
            padding: 8px 0;
            border-top: 1px solid #eef0f3;
            text-decoration: none !important;
        }

        .chat-booking:first-of-type {
            border-top: 0;
        }

        .chat-muted,
        .chat-muted-title {
            color: #8a8f98;
            font-size: 12px;
        }

        .chat-muted-title {
            font-size: inherit;
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
            vertical-align: middle;
        }

        .chat-pill--on {
            border-color: #2ecc71;
            background: rgba(46, 204, 113, 0.1);
            color: #1e8e4f;
        }

        .chat-pill--warn {
            border-color: #f0ad4e;
            background: rgba(240, 173, 78, 0.12);
            color: #a86500;
        }

        .chat-pill--danger {
            border-color: #e74c3c;
            background: rgba(231, 76, 60, 0.08);
            color: #b03a2e;
        }

        .chat-pill--info {
            border-color: #5dade2;
            background: rgba(93, 173, 226, 0.1);
            color: #1f618d;
        }

        .chat-pill--muted {
            background: #f4f5f7;
        }

        .chat-pill--online {
            border-color: #22a7f0;
            background: rgba(34, 167, 240, 0.1);
            color: #136b9c;
        }

        .chat-pill--onsite {
            border-color: #9b59b6;
            background: rgba(155, 89, 182, 0.1);
            color: #6c3483;
        }
    </style>
@stop

@section('content')
    <div class="page-content container-fluid">
        <div class="row">

            {{-- ===================== Fil ===================== --}}
            <div class="col-md-8">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <p class="chat-muted">
                            Lecture seule. Les rendez-vous du couple apparaissent dans le fil au moment où ils changent d'état.
                        </p>

                        @if ($hasOlder)
                            <div class="chat-older">
                                <a href="{{ route('booking.conversations.show', ['conversationId' => $conversation->id, 'before' => $messages->first()->id]) }}"
                                   class="btn btn-default btn-sm">Messages plus anciens</a>
                            </div>
                        @endif

                        @if ($messages->isEmpty())
                            <div class="chat-empty">Aucun message.</div>
                        @else
                            <div class="chat-thread">
                                @php $lastDay = null; @endphp
                                @foreach ($messages as $message)
                                    @php
                                        $day = $fmt($message->created_at, 'd/m/Y');
                                    @endphp
                                    @if ($day !== $lastDay)
                                        <div class="chat-day">{{ $day }}</div>
                                        @php $lastDay = $day; @endphp
                                    @endif

                                    @if ($message->isEvent())
                                        <div class="chat-event">
                                            {{ $texts[$message->id] ?? '' }}
                                            <span class="meta">
                                                {{ $fmt($message->created_at, 'H:i') }}
                                                @if ($message->booking_id)
                                                    · <a href="{{ route('booking.appointments.show', $message->booking_id) }}" target="_blank">#{{ $message->booking_id }}</a>
                                                @endif
                                            </span>
                                        </div>
                                    @else
                                        <div class="chat-msg chat-msg--{{ $message->sender }}">
                                            <div class="who">{{ $senderNames[$message->sender] ?? $message->sender }}</div>
                                            <div class="chat-bubble">{{ $message->body }}</div>
                                            <div class="meta">
                                                {{ $fmt($message->created_at, 'H:i') }}
                                                @if (($readBy[$message->sender] ?? 0) >= $message->id)
                                                    · Lu
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ===================== Colonne latérale ===================== --}}
            <div class="col-md-4 chat-side">

                {{-- --- Participants --- --}}
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <h3>Participants</h3>
                        <dl>
                            <dt>Demandeur</dt>
                            <dd>
                                {{ $requester }}
                                <div class="chat-muted">{{ $conversation->user?->email }}</div>
                            </dd>
                            <dt>Expert</dt>
                            <dd>
                                <a href="{{ route('booking.experts.show', $conversation->expert_id) }}" target="_blank">{{ $expertName }}</a>
                            </dd>
                            <dt>Ouverte le</dt>
                            <dd>{{ $conversation->opened_at ? $fmt($conversation->opened_at) : "Pas encore : au premier contact depuis un thème ou au premier rendez-vous accepté" }}</dd>
                            <dt>Messages</dt>
                            <dd>
                                Demandeur : {{ $counts['user'] ?? 0 }} · Expert : {{ $counts['expert'] ?? 0 }}
                            </dd>
                            <dt>Messages bloqués</dt>
                            <dd>
                                @if ($conversation->blocked_attempts > 0)
                                    <span class="chat-pill chat-pill--danger">{{ $conversation->blocked_attempts }}</span>
                                    <span class="chat-muted">téléphone, e-mail ou lien refusés</span>
                                @else
                                    <span class="chat-muted">Aucun</span>
                                @endif
                            </dd>
                        </dl>
                    </div>
                </div>

                {{-- --- Rendez-vous du couple --- --}}
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <h3>Rendez-vous ({{ $bookings->count() }})</h3>
                        @forelse ($bookings as $booking)
                            @php
                                $status = $booking->status;
                                if ($status === 'accepted' && $booking->blocks->max('end_at')?->isPast()) {
                                    $status = 'completed';
                                }
                                [$statusLabel, $statusClass] = Appointments::STATUS[$status] ?? [$status, 'muted'];
                            @endphp
                            <a href="{{ route('booking.appointments.show', $booking->id) }}" class="chat-booking" target="_blank">
                                <strong>#{{ $booking->id }}</strong> {{ $booking->offer_title }}
                                <div class="chat-muted">
                                    <span class="chat-pill chat-pill--{{ $statusClass }}">{{ $statusLabel }}</span>
                                    <span class="chat-pill chat-pill--{{ $booking->mode }}">{{ ExpertOfferMode::MODE_LABELS[$booking->mode] ?? $booking->mode }}</span>
                                    {{ $fmt($booking->blocks->min('start_at'), 'd/m/Y') }}
                                </div>
                            </a>
                        @empty
                            <p class="chat-muted">Aucun rendez-vous.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
