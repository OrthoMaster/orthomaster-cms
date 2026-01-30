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
    public $paymentType;

    /**
     * Create a new message instance.
     */
    public function __construct(string $userId, string $cartId, string $paymentType)
    {
        $this->userId = $userId;
        $this->cartId = $cartId;
        $this->paymentType = $paymentType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = "Votre convention de formation";
        if ($this->paymentType == '1') {
            $subject = "Votre convention OPCO-EP";
        }
        if ($this->paymentType == '2') {
            $subject = "Votre convention de formation FIFPL";
        }

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
        $view = 'signed-contract';

        if ($this->paymentType == '1') {
            $view = "signed-contract-opco";
        }
        if ($this->paymentType == '2') {
            $view = "signed-contract-fifpl";
        }

        return new Content(
            view: 'emails.'.$view,
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

        // Contracts
        $contracts = Contract::where('cart_id', $this->cartId)
            // ->where(function ($query) {
            //     $query->where('created_by', $this->userId)
            //         ->orWhere('beneficiary_id', $this->userId);
            // })
            ->where('beneficiary_id', $this->userId)
            ->where('type', 'signed')
            ->get();

        foreach ($contracts as $contract) {
            if (!empty($contract->document)) {
                $attachments[] = Attachment::fromPath($contract->document);
            }
        }

        // Programme Pdf
        $progPdf = Contract::where('cart_id', $this->cartId)
            ->where('type', 'programme')
            ->first();
        if ($progPdf) {
            $attachments[] = Attachment::fromPath($progPdf->document);
        }

        return $attachments;
    }
}
