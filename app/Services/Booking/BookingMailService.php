<?php

namespace App\Services\Booking;

use App\Jobs\SendBookingMailJob;
use App\Mail\BookingNotificationMail;
use App\Models\Booking;
use App\Models\BookingBlock;
use App\Models\BookingEvent;
use App\Models\BookingWindow;
use App\Models\ExpertOfferMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * E-mails des rendez-vous experts (pas de notification push), déclenchés par le journal
 * (BookingEventMailObserver) : un seul point d'accroche, les services n'ont rien à appeler.
 *
 *  - submitted     → expert   : nouvelle demande, délai de réponse ;
 *  - accepted      → demandeur: rendez-vous confirmé, montant débité ;
 *  - declined      → demandeur: demande refusée, montant libéré ;
 *  - expired       → demandeur: sans réponse dans le délai, montant libéré ;
 *  - cancelled     → l'autre partie (les deux si l'administration ou le système annule) ;
 *  - meet_created  → les deux : le lien de la visio est prêt (créé 1 h avant, ou à la main) ;
 *  - reminder_sent → les deux : rappel 24 h avant (booking:send-reminders).
 *
 * Liens vers l'application : go.orthomaster.fr/open/tutorat/… (ouvre l'application,
 * ou la version web sur ordinateur).
 */
class BookingMailService
{
    private const PERIODS = ['morning' => 'matin', 'afternoon' => 'après-midi'];

    public function forEvent(BookingEvent $event): void
    {
        $booking = Booking::with(['blocks', 'windows', 'offer', 'expert', 'user'])->find($event->booking_id);
        if (!$booking) {
            return;
        }

        // Demande transmise : l'expert est prévenu (ci-dessous), le demandeur reçoit sa confirmation.
        if ($event->type === 'submitted') {
            $this->requestConfirmation($booking);
        }

        match ($event->type) {
            'submitted'    => $this->submitted($booking),
            'accepted'     => $this->accepted($booking),
            'declined'     => $this->declined($booking, $event),
            'expired'      => $this->expired($booking),
            'cancelled'    => $this->cancelled($booking, $event),
            'meet_created' => $this->meetCreated($booking, $event),
            'reminder_sent' => $this->reminder($booking, $event),
            default        => null,
        };
    }

    /* ===================== ÉVÉNEMENTS ===================== */

    private function submitted(Booking $booking): void
    {
        [$email, $name] = $this->expertRecipient($booking);
        if (!$email) {
            return;
        }

        $client = $this->clientName($booking);
        $details = $this->subject($booking)
            + ['Format' => $this->modeText($booking)]
            + $this->schedule($booking)
            + ($booking->payment_required ? ['Montant' => $this->money($booking->amount_ttc) . ' TTC'] : ['Montant' => 'Offert'])
            + ($booking->respond_by ? ['À répondre avant' => $this->dateTime($booking->respond_by)] : []);

        $this->send($email, new BookingNotificationMail(
            subjectLine: 'Nouvelle demande de rendez-vous de ' . $client,
            heading: 'Nouvelle demande de rendez-vous',
            name: $name,
            lines: [
                '<strong>' . e($client) . '</strong> vous a envoyé une demande de '
                    . ($booking->isConsultation() ? 'consultation' : 'rendez-vous') . '.',
                $booking->windows->isNotEmpty() && $booking->blocks->isEmpty()
                    ? "La demande est hors calendrier : choisissez l'horaire parmi les créneaux proposés en l'acceptant."
                    : 'Acceptez-la ou refusez-la depuis l\'application.',
            ],
            details: $details,
            action: ['text' => 'Voir la demande', 'url' => $this->link('demande', $booking)],
            note: $booking->respond_by
                ? 'Sans réponse avant le ' . $this->dateTime($booking->respond_by) . ', la demande expirera et le montant réservé sera libéré.'
                : null,
        ));
    }

    /**
     * « Confirmation de votre demande » au demandeur : trace de la demande et du montant réservé
     * (non débité) en attendant la réponse de l'expert. Pas une facture : celle-ci (VE) suit l'acceptation.
     */
    private function requestConfirmation(Booking $booking): void
    {
        [$email, $name] = $this->clientRecipient($booking);
        if (!$email) {
            return;
        }

        $reference = $booking->reference();
        $details = ['Référence' => $reference, 'Date de la demande' => $this->dateTime($booking->requested_at ?? now())]
            + ['Expert' => $this->expertName($booking)]
            + $this->subject($booking)
            + ['Format' => $this->modeText($booking)]
            + $this->schedule($booking)
            + ['Participant' => $this->clientName($booking)]
            + ($booking->payment_required
                ? [
                    'Prix HT'  => $this->money($booking->amount_ht),
                    'TVA (' . rtrim(rtrim(number_format((float) $booking->tva_rate, 2, ',', ''), '0'), ',') . ' %)' => $this->money($booking->tva_amount),
                    'Total TTC' => $this->money($booking->amount_ttc),
                    'Paiement' => 'Réservé sur votre carte le ' . Carbon::parse($booking->requested_at ?? now())->setTimezone('Europe/Paris')->format('d/m/Y') . ' — non débité',
                ]
                : ['Montant' => 'Offert']);

        $this->send($email, new BookingNotificationMail(
            subjectLine: "Confirmation de votre demande {$reference}",
            heading: 'Confirmation de votre demande',
            name: $name,
            lines: array_values(array_filter([
                'Votre demande de ' . ($booking->isConsultation() ? 'consultation' : 'rendez-vous') . ' avec <strong>'
                    . e($this->expertName($booking)) . '</strong> a bien été transmise. Elle est en attente de sa réponse.',
                $booking->payment_required
                    ? 'Le montant est <strong>réservé</strong> sur votre carte, mais <strong>pas encore débité</strong> : il ne le sera que si l\'expert accepte votre demande. Vous recevrez alors votre facture.'
                    : null,
            ])),
            details: $details,
            action: ['text' => 'Voir la demande', 'url' => $this->link('rendez-vous', $booking)],
            note: ltrim(($booking->respond_by
                    ? 'Sans réponse avant le ' . $this->dateTime($booking->respond_by) . ', ou en cas de refus, '
                        . ($booking->payment_required ? 'la réservation est libérée et rien n\'est débité.' : 'la demande est close.')
                    : '')
                . ' Ce document n\'est pas une facture.'),
        ));
    }

    private function accepted(Booking $booking): void
    {
        [$email, $name] = $this->clientRecipient($booking);
        if (!$email) {
            return;
        }

        $online = $booking->mode !== ExpertOfferMode::ONSITE;
        $details = ['Expert' => $this->expertName($booking)]
            + $this->subject($booking)
            + ['Format' => $this->modeText($booking)]
            + $this->schedule($booking)
            + ($booking->payment_required ? ['Montant débité' => $this->money($booking->amount_ttc) . ' TTC'] : []);

        $this->send($email, new BookingNotificationMail(
            subjectLine: 'Votre rendez-vous avec ' . $this->expertName($booking) . ' est confirmé',
            heading: 'Rendez-vous confirmé',
            name: $name,
            lines: [
                '<strong>' . e($this->expertName($booking)) . '</strong> a accepté votre demande : votre rendez-vous est confirmé.',
            ],
            details: $details,
            action: ['text' => 'Voir le rendez-vous', 'url' => $this->link('rendez-vous', $booking)],
            note: $online
                ? "Le lien de la visio sera disponible dans l'application 1 heure avant le début ; vous le recevrez aussi par e-mail."
                : null,
        ));
    }

    private function declined(Booking $booking, BookingEvent $event): void
    {
        [$email, $name] = $this->clientRecipient($booking);
        if (!$email) {
            return;
        }

        $reason = $event->data['reason'] ?? null;
        $this->send($email, new BookingNotificationMail(
            subjectLine: 'Votre demande de rendez-vous n\'a pas été acceptée',
            heading: 'Demande non acceptée',
            name: $name,
            lines: array_values(array_filter([
                '<strong>' . e($this->expertName($booking)) . '</strong> n\'a pas pu accepter votre demande.',
                $booking->payment_required ? 'Le montant réservé sur votre carte a été libéré : rien ne vous a été débité.' : null,
                'Vous pouvez proposer d\'autres dates ou choisir un autre expert dans l\'application.',
            ])),
            details: $this->subject($booking) + ($reason ? ['Motif' => $reason] : []),
            action: ['text' => 'Voir la demande', 'url' => $this->link('rendez-vous', $booking)],
        ));
    }

    private function expired(Booking $booking): void
    {
        [$email, $name] = $this->clientRecipient($booking);
        if (!$email) {
            return;
        }

        $this->send($email, new BookingNotificationMail(
            subjectLine: 'Votre demande de rendez-vous a expiré',
            heading: 'Demande expirée',
            name: $name,
            lines: array_values(array_filter([
                '<strong>' . e($this->expertName($booking)) . '</strong> n\'a pas répondu à votre demande dans le délai prévu.',
                $booking->payment_required ? 'Le montant réservé sur votre carte a été libéré : rien ne vous a été débité.' : null,
                'Vous pouvez envoyer une nouvelle demande depuis l\'application.',
            ])),
            details: $this->subject($booking),
            action: ['text' => 'Voir la demande', 'url' => $this->link('rendez-vous', $booking)],
        ));
    }

    private function cancelled(Booking $booking, BookingEvent $event): void
    {
        // Qui a annulé : la partie au nom de laquelle l'administration agit, sinon l'auteur.
        $party = $event->on_behalf_of ?: $event->actor;
        $wasAccepted = $booking->events()->where('type', 'accepted')->exists();
        $refund = isset($event->data['refund']) ? (float) $event->data['refund'] : (float) $booking->refunded_amount;
        $what = $wasAccepted ? 'le rendez-vous' : 'la demande';

        $toExpert = in_array($party, [BookingEvent::ACTOR_USER, BookingEvent::ACTOR_ADMIN, BookingEvent::ACTOR_SYSTEM], true);
        $toClient = in_array($party, [BookingEvent::ACTOR_EXPERT, BookingEvent::ACTOR_ADMIN, BookingEvent::ACTOR_SYSTEM], true);
        $by = match ($party) {
            BookingEvent::ACTOR_USER   => $this->clientName($booking),
            BookingEvent::ACTOR_EXPERT => $this->expertName($booking),
            default                    => "l'équipe OrthoMaster",
        };

        if ($toExpert) {
            [$email, $name] = $this->expertRecipient($booking);
            if ($email) {
                $this->send($email, new BookingNotificationMail(
                    subjectLine: ucfirst($what) . ' avec ' . $this->clientName($booking) . ' est annulé' . ($wasAccepted ? '' : 'e'),
                    heading: ucfirst($what) . ' annulé' . ($wasAccepted ? '' : 'e'),
                    name: $name,
                    lines: [ucfirst($what) . ' de <strong>' . e($this->clientName($booking)) . '</strong> a été annulé' . ($wasAccepted ? '' : 'e') . ' par ' . e($by) . '.'],
                    details: $this->subject($booking) + $this->schedule($booking),
                    action: ['text' => 'Voir la demande', 'url' => $this->link('demande', $booking)],
                ));
            }
        }

        if ($toClient) {
            [$email, $name] = $this->clientRecipient($booking);
            if ($email) {
                $money = !$booking->payment_required ? null
                    : (!$wasAccepted
                        ? 'Le montant réservé sur votre carte a été libéré : rien ne vous a été débité.'
                        : ($refund > 0 ? 'Vous serez remboursé de ' . $this->money($refund) . ' sur votre carte.' : null));
                $this->send($email, new BookingNotificationMail(
                    subjectLine: 'Votre ' . ($wasAccepted ? 'rendez-vous' : 'demande') . ' avec ' . $this->expertName($booking) . ' est annulé' . ($wasAccepted ? '' : 'e'),
                    heading: ucfirst($what) . ' annulé' . ($wasAccepted ? '' : 'e'),
                    name: $name,
                    lines: array_values(array_filter([
                        'Votre ' . ($wasAccepted ? 'rendez-vous' : 'demande') . ' avec <strong>' . e($this->expertName($booking)) . '</strong> a été annulé' . ($wasAccepted ? '' : 'e') . ' par ' . e($by) . '.',
                        $money,
                    ])),
                    details: $this->subject($booking) + $this->schedule($booking),
                    action: ['text' => 'Voir le rendez-vous', 'url' => $this->link('rendez-vous', $booking)],
                ));
            }
        }
    }

    private function meetCreated(Booking $booking, BookingEvent $event): void
    {
        if ($booking->status !== Booking::STATUS_ACCEPTED) {
            return;
        }
        $block = $booking->blocks->firstWhere('id', (int) ($event->data['block_id'] ?? 0));
        if (!$block || !$block->meet_url || ($block->end_at && $block->end_at->isPast())) {
            return;
        }

        $details = $this->subject($booking) + ['Horaire' => $this->blockText($booking, $block), 'Lien de la visio' => $block->meet_url];

        foreach ([['demande', $this->expertRecipient($booking), $this->clientName($booking)], ['rendez-vous', $this->clientRecipient($booking), $this->expertName($booking)]] as [$kind, [$email, $name], $other]) {
            if (!$email) {
                continue;
            }
            $this->send($email, new BookingNotificationMail(
                subjectLine: 'Le lien de votre visio avec ' . $other . ' est prêt',
                heading: 'Votre visio est prête',
                name: $name,
                lines: [
                    'Le lien de votre visio avec <strong>' . e($other) . '</strong> est prêt. Il fonctionne dès maintenant, depuis l\'application ou depuis ce lien.',
                ],
                details: $details,
                action: ['text' => 'Rejoindre la visio', 'url' => $block->meet_url],
                note: 'Vous retrouvez aussi ce lien dans l\'application : ' . $this->link($kind, $booking),
            ));
        }
    }

    /** Rappel 24 h avant : les demi-journées du jour concerné, aux deux parties. */
    private function reminder(Booking $booking, BookingEvent $event): void
    {
        if ($booking->status !== Booking::STATUS_ACCEPTED) {
            return;
        }
        $ids = array_map('intval', $event->data['block_ids'] ?? []);
        $blocks = $booking->blocks->filter(fn (BookingBlock $b) => in_array((int) $b->id, $ids, true))->sortBy('start_at');
        if ($blocks->isEmpty()) {
            return;
        }

        $online = $booking->mode !== ExpertOfferMode::ONSITE;
        $first = $blocks->first();
        $when = $first->start_at->copy()->setTimezone('Europe/Paris')->isTomorrow() ? 'demain' : 'le ' . $this->day($first->date);
        $schedule = $blocks->map(function (BookingBlock $b) use ($booking) {
            $place = $b->location_text;

            return $place ? $this->blockText($booking, $b) . "\n" . $place : $this->blockText($booking, $b);
        })->implode("\n");
        $details = $this->subject($booking) + ['Format' => $this->modeText($booking), 'Horaire' => $schedule];
        $note = $online
            ? "Le lien de la visio sera disponible 1 heure avant le début, dans l'application et par e-mail."
            : 'Pensez à prévoir votre trajet : l\'adresse et l\'itinéraire sont dans l\'application.';

        foreach ([['demande', $this->expertRecipient($booking), $this->clientName($booking)], ['rendez-vous', $this->clientRecipient($booking), $this->expertName($booking)]] as [$kind, [$email, $name], $other]) {
            if (!$email) {
                continue;
            }
            $this->send($email, new BookingNotificationMail(
                subjectLine: 'Rappel : votre rendez-vous avec ' . $other . ' ' . $when,
                heading: 'Rappel de rendez-vous',
                name: $name,
                lines: ['Petit rappel : vous avez rendez-vous ' . e($when) . ' avec <strong>' . e($other) . '</strong>.'],
                details: $details,
                action: ['text' => 'Voir le rendez-vous', 'url' => $this->link($kind, $booking)],
                note: $note,
            ));
        }
    }

    /* ===================== CONTENU ===================== */

    /** Lien vers l'application (go.orthomaster.fr/open/… ouvre l'application). */
    private function link(string $kind, Booking $booking): string
    {
        return rtrim((string) config('app.short_link_domain', 'https://go.orthomaster.fr'), '/') . "/open/tutorat/{$kind}/{$booking->id}";
    }

    /** Thème ou sujet de la consultation. */
    private function subject(Booking $booking): array
    {
        if ($booking->isConsultation()) {
            return ['Consultation' => ($booking->duration_minutes ? $booking->duration_minutes . ' min' : '') . ($booking->subject ? "\n" . $booking->subject : '')];
        }

        return ['Thème' => $booking->offer?->title ?? '—'];
    }

    private function modeText(Booking $booking): string
    {
        return $booking->mode === ExpertOfferMode::ONSITE ? 'En présentiel' : 'En visio';
    }

    /** Horaire : demi-journées (et lieux), ou créneaux proposés tant que l'expert n'a pas choisi. */
    private function schedule(Booking $booking): array
    {
        if ($booking->blocks->isNotEmpty()) {
            $lines = $booking->blocks->sortBy('start_at')->map(function (BookingBlock $b) use ($booking) {
                $text = $this->blockText($booking, $b);
                $place = $b->location_text;

                return $place ? $text . "\n" . $place : $text;
            });

            return [$booking->blocks->count() > 1 ? 'Horaires' : 'Horaire' => $lines->implode("\n")];
        }

        if ($booking->windows->isNotEmpty()) {
            return [
                ($booking->windows->count() > 1 ? 'Créneaux proposés' : 'Créneau proposé')
                    => $booking->windows->map(fn (BookingWindow $w) => $this->windowText($w))->implode("\n"),
            ];
        }

        return [];
    }

    private function blockText(Booking $booking, BookingBlock $b): string
    {
        $day = $this->day($b->date);
        $hours = ($b->start_local ?? '') . ' – ' . ($b->end_local ?? '');

        return $booking->isConsultation()
            ? "{$day}, {$hours}"
            : "{$day}, " . (self::PERIODS[$b->period] ?? $b->period) . " ({$hours})";
    }

    private function windowText(BookingWindow $w): string
    {
        $start = $w->start_time ?? (self::PERIODS[$w->start_period] ?? '');
        $end = $w->end_time ?? (self::PERIODS[$w->end_period] ?? '');

        return $w->start_date->isSameDay($w->end_date)
            ? $this->day($w->start_date) . ", {$start} – {$end}"
            : $this->day($w->start_date) . " {$start} → " . $this->day($w->end_date) . " {$end}";
    }

    private function day($date): string
    {
        return ucfirst(Carbon::parse($date)->locale('fr')->translatedFormat('l j F Y'));
    }

    private function dateTime($date): string
    {
        return Carbon::parse($date)->setTimezone('Europe/Paris')->locale('fr')->translatedFormat('l j F Y \à H\hi');
    }

    private function money($amount): string
    {
        return number_format((float) $amount, 2, ',', ' ') . ' €';
    }

    /* ===================== DESTINATAIRES ===================== */

    private function clientName(Booking $booking): string
    {
        $u = $booking->user;

        $name = $u ? trim(($u->first_name ?? '') . ' ' . ($u->name ?? '')) : '';

        return $name !== '' ? $name : 'Un confrère';
    }

    private function expertName(Booking $booking): string
    {
        $e = $booking->expert;
        if (!$e) {
            return "l'expert";
        }

        return trim((ExpertProfileService::honorificShort($e->honorific) ?? '') . ' ' . $e->name);
    }

    /** [e-mail, prénom] du demandeur. */
    private function clientRecipient(Booking $booking): array
    {
        $u = $booking->user;

        return [$u?->email, $this->clientName($booking)];
    }

    /** [e-mail, nom] de l'expert : celui du compte relié (experts.user_id). */
    private function expertRecipient(Booking $booking): array
    {
        $userId = $booking->expert?->user_id ?? DB::table('experts')->where('id', $booking->expert_id)->value('user_id');
        $u = $userId ? DB::table('users')->where('id', $userId)->first(['email', 'name', 'first_name']) : null;

        return [$u?->email, $this->expertName($booking)];
    }

    private function send(?string $to, BookingNotificationMail $mail): void
    {
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        SendBookingMailJob::dispatch($to, $mail);
    }
}
