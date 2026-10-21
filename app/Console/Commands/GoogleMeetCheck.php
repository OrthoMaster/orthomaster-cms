<?php

namespace App\Console\Commands;

use App\Services\Google\GoogleMeetService;
use Illuminate\Console\Command;

/**
 * Vérifie la configuration Google Meet en créant une salle de test.
 * php artisan meet:check
 */
class GoogleMeetCheck extends Command
{
    protected $signature = 'meet:check';

    protected $description = 'Crée une salle Google Meet de test pour vérifier les identifiants';

    public function handle(GoogleMeetService $meet): int
    {
        $this->line('Fichier de clé : ' . config('services.google_meet.credentials'));
        $this->line('Au nom de      : ' . config('services.google_meet.subject'));
        $this->line('Accès          : ' . config('services.google_meet.access_type'));

        try {
            $space = $meet->createSpace();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('OK, salle créée : ' . $space['url']);
        $this->line('Identifiant Google : ' . $space['name']);
        $this->line('Ouvrez le lien dans une fenêtre privée (sans compte Google) pour vérifier que l\'on entre sans attendre.');

        return self::SUCCESS;
    }
}