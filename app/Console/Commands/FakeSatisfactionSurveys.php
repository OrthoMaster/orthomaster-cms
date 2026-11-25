<?php

namespace App\Console\Commands;

use App\Services\Satisfaction\SatisfactionFakeDataService;
use Illuminate\Console\Command;

/**
 * Questionnaires de satisfaction fictifs, pour présenter l'écran de suivi qualité.
 *
 *   php artisan satisfaction:fake               80 questionnaires fictifs (vrais apprenants et paquets, réponses inventées)
 *   php artisan satisfaction:fake --count=150
 *   php artisan satisfaction:fake --clear       supprimer tous les questionnaires fictifs
 *
 * Marqués is_fake : aucun e-mail ne part, et la page publique n'est jamais envoyée à ces apprenants.
 */
class FakeSatisfactionSurveys extends Command
{
    protected $signature = 'satisfaction:fake
        {--count=80 : Nombre de questionnaires fictifs à créer}
        {--clear : Supprimer les questionnaires fictifs au lieu d\'en créer}
        {--force : Ne pas demander de confirmation}';

    protected $description = 'Crée (ou supprime) des questionnaires de satisfaction fictifs à partir des vrais apprenants et formations';

    public function handle(SatisfactionFakeDataService $fake): int
    {
        if ($this->option('clear')) {
            $this->info($fake->clear() . ' questionnaire(s) fictif(s) supprimé(s).');

            return Command::SUCCESS;
        }

        if (app()->environment('production') && !$this->option('force')
            && !$this->confirm('Base de PRODUCTION : créer des données fictives (visibles dans le CMS jusqu\'à --clear) ?', false)) {
            return Command::FAILURE;
        }

        $count = max(1, (int) $this->option('count'));
        $created = $fake->generate($count);

        $this->info("{$created} questionnaire(s) fictif(s) créé(s)" . ($created < $count ? ' (pas assez d\'apprenants inscrits pour en faire plus)' : '') . '.');
        $this->line('Pour les supprimer : php artisan satisfaction:fake --clear');

        return Command::SUCCESS;
    }
}
