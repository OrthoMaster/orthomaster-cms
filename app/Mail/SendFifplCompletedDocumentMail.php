<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendFifplCompletedDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public $userId;
    public $invoice;

    /**
     * Create a new message instance.
     */
    public function __construct(string $userId, Invoice $invoice)
    {
        $this->userId = $userId;
        $this->invoice = $invoice;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = "FIFPL OrthoMaster : Étape finale";

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $user = User::find($this->userId);
        
        return new Content(
            view: 'emails.paid-invoice-and-certification-fifpl',
            with: [
                'mailTo' => $user->email, // footer
                'user' => $user,
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
        $attachments = [];
        if ($this->invoice->document) {
            $attachments[] = Attachment::fromPath($this->invoice->document);
        }
        if ($this->invoice->certification) {
            $attachments[] = Attachment::fromPath($this->invoice->certification);
        }

        return $attachments;
    }
}
