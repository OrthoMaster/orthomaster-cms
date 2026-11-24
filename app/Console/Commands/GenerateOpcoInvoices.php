<?php

namespace App\Console\Commands;

use App\Jobs\GenerateOpcoInvoicesJob;
use App\Services\Opco\OpcoInvoiceService;
use Illuminate\Console\Command;

/**
 * php artisan opco:invoices              → conventions récentes, en file d'attente (tâche du soir)
 * php artisan opco:invoices --all        → aussi les anciennes conventions (rattrapage)
 * php artisan opco:invoices --all --now  → rattrapage tout de suite, avec le détail
 */
class GenerateOpcoInvoices extends Command
{
    protected $signature = 'opco:invoices
        {--all : Aussi les anciennes conventions (fin de formation déjà passée)}
        {--now : Créer tout de suite au lieu de passer par la file d\'attente}';

    protected $description = 'Crée la facture (et le certificat) de chaque convention OPCO-EP signée qui n\'en a pas';

    public function handle(OpcoInvoiceService $opco): int
    {
        if (!$this->option('now')) {
            GenerateOpcoInvoicesJob::dispatch((bool) $this->option('all'));
            $this->info('Mis en file d\'attente.');
            return Command::SUCCESS;
        }

        $all = (bool) $this->option('all');
        $this->info($opco->contractsWithoutInvoice(!$all)->count() . ' convention(s) sans facture.');
        $result = $opco->generateMissing($all);

        if ($result['locked']) {
            $this->warn('Une création est déjà en cours.');
            return Command::FAILURE;
        }

        $this->info(count($result['created']) . ' facture(s) créée(s) : ' . implode(', ', $result['created']));
        if ($result['failed']) {
            $this->error(count($result['failed']) . ' échec(s) : ' . implode(', ', $result['failed']));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
