<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendToAdProviderMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $users;
    public string $video;

    /**
     * Create a new message instance.
     */
    public function __construct(array $users, string $video)
    {
        $this->users = $users;
        $this->video = $video;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Votre annonce OrthoMaster suscite de l'intérêt !",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.ads.to-provider',
            with: [
                'users' => $this->users,
                'video' => $this->video,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
