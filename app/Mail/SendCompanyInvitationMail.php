<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendCompanyInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $mailTo;
    public $joinCode;

    /**
     * Create a new message instance.
     */
    public function __construct(string $mailTo, User $user, string $joinCode)
    {
        $this->user = $user;
        $this->mailTo = $mailTo;
        $this->joinCode = $joinCode;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitation à rejoindre le groupe',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.invite-user-join-company',
            with: [
                'mailTo' => $this->mailTo,
                'user' => $this->user,
                'joinCode' => $this->joinCode
            ]
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
