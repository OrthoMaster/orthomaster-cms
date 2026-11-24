<?php

namespace App\Mail;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Fin de formation OPCO-EP : au manager, un mois après la signature de la convention,
 * avec la facture (adressée à l'OPCO) et les certificats de réalisation.
 */
class OpcoCompletedDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Invoice $invoice,
        public Contract $contract,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'OPCO-EP OrthoMaster : fin de formation',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.opco-completed-documents',
            with: [
                'mailTo'   => $this->user->email, // footer
                'user'     => $this->user,
                'contract' => $this->contract,
            ]
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];
        if ($this->invoice->document) {
            $attachments[] = Attachment::fromPath($this->invoice->document)
                ->as('Facture_' . str_replace('/', '_', $this->invoice->no) . '.pdf')
                ->withMime('application/pdf');
        }
        if ($this->invoice->certification) {
            $attachments[] = Attachment::fromPath($this->invoice->certification)
                ->as('Certificats_realisation_' . $this->contract->no . '.pdf')
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
