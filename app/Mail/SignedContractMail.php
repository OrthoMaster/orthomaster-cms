<?php

namespace App\Mail;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignedContractMail extends Mailable
{
    use Queueable, SerializesModels;

    public $userId;
    public $cartId;

    /**
     * Create a new message instance.
     */
    public function __construct(string $userId, string $cartId)
    {
        $this->userId = $userId;
        $this->cartId = $cartId;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Les devis',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $user = User::find($this->userId);

        return new Content(
            view: 'emails.signed-contract',
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
        $contracts = Contract::where('cart_id', $this->cartId)->get();

        foreach ($contracts as $contract) {
            if (!empty($contract->document)) {
                $attachments[] = Attachment::fromPath($contract->document);
            }
        }

        return $attachments;
    }
}
