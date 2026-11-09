<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail des rendez-vous experts (Tutorat). Contenu préparé par BookingMailService.
 *
 * @param array<int, string>     $lines   paragraphes (HTML échappé par l'appelant)
 * @param array<string, string>  $details « libellé » => valeur (texte brut)
 * @param array{text:string,url:string}|null $action bouton
 */
class BookingNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $heading,
        public string $name,
        public array $lines,
        public array $details = [],
        public ?array $action = null,
        public ?string $note = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-notification',
            with: [
                'heading' => $this->heading,
                'name'    => $this->name,
                'lines'   => $this->lines,
                'details' => $this->details,
                'action'  => $this->action,
                'note'    => $this->note,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
