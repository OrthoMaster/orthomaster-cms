<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatisfactionSurvey extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_fake'          => 'boolean',
        'package_ids'      => 'array',
        'purchased_at'     => 'datetime',
        'sent_at'          => 'datetime',
        'reminded_at'      => 'datetime',
        'answered_at'      => 'datetime',
        'difficulties_flg' => 'boolean',
        'recontact_flg'    => 'boolean',
        'score'            => 'float',
    ];

    /** Questions notées de 1 à 5 : colonne => libellé. Leur moyenne donne le score. */
    public const RATINGS = [
        'rating_global'     => 'Globalement, quel est votre niveau de satisfaction concernant cette formation ?',
        'rating_objectives' => 'Les objectifs annoncés de la formation ont-ils été atteints ?',
        'rating_content'    => 'Le contenu était-il adapté à votre niveau et à votre pratique professionnelle ?',
        'rating_trainers'   => 'Comment évaluez-vous la qualité pédagogique des intervenants (clarté, expertise) ?',
        'rating_platform'   => 'La plateforme (application / web) était-elle simple et agréable à utiliser ?',
        'rating_skills'     => 'Dans quelle mesure cette formation a-t-elle amélioré vos compétences ?',
    ];

    public const PRACTICE = [
        'regular'    => 'Oui, régulièrement',
        'occasional' => 'Oui, ponctuellement',
        'not_yet'    => 'Pas encore',
        'no'         => 'Non',
    ];

    /** package_user.progress au-delà duquel une formation compte comme terminée (dashboard suivi, données fictives). */
    public const COMPLETION_MIN = 75;

    /** Seuil « satisfait » sur la question globale (taux de satisfaction, Qualiopi ind. 2). */
    public const SATISFIED_MIN = 4;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Compte de l'apprenant, sinon l'adresse saisie dans le CMS (questionnaire de test). */
    public function recipientEmail(): ?string
    {
        return $this->user?->email ?? $this->email;
    }

    public function recipientName(): string
    {
        $name = trim(($this->user?->first_name ?? '') . ' ' . ($this->user?->name ?? ''));

        return $name !== '' ? $name : (string) $this->email;
    }

    public function url(): string
    {
        return rtrim(config('app.auth_url'), '/') . '/satisfaction/' . $this->token;
    }
}
