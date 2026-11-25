<?php

namespace App\Console\Commands;

use App\Services\Opco\OpcoInvoiceService;
use Illuminate\Console\Command;

/**
 * Traitement complet des conventions OPCO-EP : facture, attestation, certificats aux apprenants.
 *
 *   php artisan opco:process                         simulation (rien n'est fait) : ce qui serait fait, convention par convention
 *   php artisan opco:process --run                   le faire vraiment
 *   php artisan opco:process --run --contract=12,15  seulement ces conventions (id)
 *   php artisan opco:process --run --exclude=3,7     sans ces conventions (id), p. ex. déjà facturées à la main dans Odoo
 *   php artisan opco:process --run --no-email        facture + attestation, sans e-mail aux apprenants
 *   ... --csv=storage/app/opco.csv                   rapport en CSV
 *   ... --force                                      sans confirmation
 *   php artisan opco:process --run --daily           tâche quotidienne (08:30) : conventions signées depuis le
 *                                                    25/11/2026 + celles qui ont déjà leur facture
 *
 * Formation en cours (signature + 1 mois pas atteint) : facture seulement, datée de signature + 1 mois ;
 * attestation et e-mails lors d'un passage après cette date. Rejouable sans doublon.
 */
class ProcessOpco extends Command
{
    protected $signature = 'opco:process
        {--run : Faire vraiment (sans cette option : simulation)}
        {--contract= : Seulement ces conventions (id séparés par des virgules)}
        {--exclude= : Laisser de côté ces conventions (id séparés par des virgules)}
        {--no-email : Ne pas envoyer les certificats aux apprenants}
        {--csv= : Écrire le rapport dans ce fichier CSV}
        {--daily : Tâche quotidienne : conventions signées depuis le 25/11/2026 + celles déjà facturées ; sans confirmation}
        {--force : Ne pas demander de confirmation (tâche planifiée)}';

    protected $description = 'OPCO-EP : facture, attestation et certificats aux apprenants pour toutes les conventions signées';

    public function handle(OpcoInvoiceService $opco): int
    {
        $dryRun = !$this->option('run');
        $daily = (bool) $this->option('daily');
        $ids = fn (?string $v) => array_values(array_filter(array_map('intval', explode(',', (string) $v))));

        $this->info($dryRun
            ? 'SIMULATION — rien ne sera créé ni envoyé (ajoutez --run pour le faire).'
            : 'EXÉCUTION — factures Odoo, attestations et e-mails réels.');

        if ($daily) {
            $this->info('Tâche quotidienne : conventions signées depuis le 25/11/2026 et conventions déjà facturées.');
        }

        if (!$dryRun && !$this->option('force') && !$daily && !$this->confirm('Créer les factures dans Odoo et envoyer les e-mails maintenant ?', false)) {
            return Command::FAILURE;
        }

        $rows = $opco->processAll(
            $dryRun,
            $ids($this->option('contract')),
            $ids($this->option('exclude')),
            !$this->option('no-email'),
            function (array $row) {
                $line = "{$row['contract']} · {$row['company']} · signée le {$row['signed']} → " . implode(' ; ', $row['steps']);
                if ($row['error']) {
                    $this->error($line . ' — ERREUR : ' . $row['error']);
                } elseif ($row['status'] === 'à vérifier') {
                    $this->warn($line);
                } else {
                    $this->line($line);
                }
            },
            $daily
        );

        $count = collect($rows)->countBy('status');
        $this->newLine();
        $this->info(count($rows) . ' convention(s) : '
            . collect($count)->map(fn ($n, $status) => "{$n} {$status}")->implode(', '));

        if ($csv = $this->option('csv')) {
            $fh = fopen($csv, 'w');
            fputcsv($fh, ['Convention', 'Entreprise', 'Signée le', 'Fin de formation', 'Statut', 'Étapes', 'Erreur'], ';');
            foreach ($rows as $row) {
                fputcsv($fh, [$row['contract'], $row['company'], $row['signed'], $row['end'], $row['status'], implode(' ; ', $row['steps']), $row['error']], ';');
            }
            fclose($fh);
            $this->info("Rapport : {$csv}");
        }

        return ($count['erreur'] ?? 0) > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
