<?php

namespace App\Mail;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Fin de formation OPCO-EP : à chaque apprenant, son certificat de réalisation
 * (sa page de l'attestation, jointe par OpcoInvoiceService::sendCertificates()). Pas de facture.
 */
class OpcoCertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $learner,
        public Contract $contract,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'OrthoMaster : votre certificat de réalisation',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.opco-certificate',
            with: [
                'mailTo'  => $this->learner->email, // footer
                'learner' => $this->learner,
            ]
        );
    }
}
