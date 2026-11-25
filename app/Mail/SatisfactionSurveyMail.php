<?php

namespace App\Mail;

use App\Models\SatisfactionSurvey;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Questionnaire de satisfaction à froid (3 mois après le devis), ou sa relance (6 mois).
 */
class SatisfactionSurveyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SatisfactionSurvey $survey,
        public bool $reminder = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reminder
                ? 'Rappel : votre avis sur votre formation OrthoMaster'
                : 'Votre avis sur votre formation OrthoMaster',
        );
    }

    public function content(): Content
    {
        $learner = $this->survey->user;

        return new Content(
            view: 'emails.satisfaction-survey',
            with: [
                'mailTo'   => $learner->email, // footer
                'name'     => trim($learner->first_name . ' ' . $learner->name),
                'packages' => explode("\n", $this->survey->packages_label),
                'url'      => $this->survey->url(),
                'reminder' => $this->reminder,
            ]
        );
    }
}
