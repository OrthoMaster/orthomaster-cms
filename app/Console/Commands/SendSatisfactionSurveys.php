<?php

namespace App\Console\Commands;

use App\Services\Satisfaction\SatisfactionSurveyService;
use Illuminate\Console\Command;

/**
 * Questionnaires de satisfaction (Qualiopi ind. 30) : un par apprenant et par devis signé.
 *
 *   php artisan satisfaction:send                  simulation : ce qui serait créé et envoyé
 *   php artisan satisfaction:send --run            le faire vraiment (historique compris : devis de plus de 3 mois)
 *   php artisan satisfaction:send --run --daily    tâche quotidienne : devis datés à partir du 25/08/2026 + relances
 *
 * Envoi 3 mois après le devis, une relance à 6 mois sans réponse (au moins 30 jours après l'envoi).
 */
class SendSatisfactionSurveys extends Command
{
    protected $signature = 'satisfaction:send
        {--run : Faire vraiment (sans cette option : simulation)}
        {--daily : Tâche quotidienne : sans l\'historique, sans confirmation}
        {--force : Ne pas demander de confirmation}';

    protected $description = 'Questionnaires de satisfaction à froid : création, envoi à 3 mois, relance à 6 mois';

    public function handle(SatisfactionSurveyService $service): int
    {
        $dryRun = !$this->option('run');
        $daily = (bool) $this->option('daily');

        $this->info($dryRun
            ? 'SIMULATION — rien ne sera créé ni envoyé (ajoutez --run pour le faire).'
            : 'EXÉCUTION — e-mails réels.');

        if (!$dryRun && !$daily && !$this->option('force') && !$this->confirm('Envoyer les questionnaires maintenant ?', false)) {
            return Command::FAILURE;
        }

        $result = $service->process($dryRun, $daily, function (array $row) {
            $line = "#{$row['survey']} · {$row['kind']} · {$row['email']} · devis {$row['devis']} du {$row['date']}";
            $row['error'] ? $this->error($line . ' — ERREUR : ' . $row['error']) : $this->line($line);
        });

        $rows = collect($result['rows']);
        $this->newLine();
        $this->info($result['created'] . ' questionnaire(s) créé(s) · '
            . $rows->where('kind', 'envoi')->whereNull('error')->count() . ' envoi(s) · '
            . $rows->where('kind', 'relance')->whereNull('error')->count() . ' relance(s) · '
            . $rows->whereNotNull('error')->count() . ' erreur(s)');

        return Command::SUCCESS;
    }
}
