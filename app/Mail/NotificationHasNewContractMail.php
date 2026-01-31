<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationHasNewContractMail extends Mailable
{
    use Queueable, SerializesModels;

    public $cart_id;

    /**
     * Create a new message instance.
     */
    public function __construct($cart_id)
    {
        $this->cart_id = $cart_id;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Système] Nouveau Devis',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $contract = Contract::where('cart_id', $this->cart_id)
            ->with('company')->first();

        return new Content(
            view: 'emails.admin.has-new-contract',
            with: [
                'contract' => $contract,
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
