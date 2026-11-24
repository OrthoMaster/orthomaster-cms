<?php

namespace App\Console\Commands;

use App\Services\Opco\OpcoInvoiceService;
use Illuminate\Console\Command;

/** E-mail de fin de formation (facture + certificats) aux managers dont la date d'envoi est arrivée. */
class SendOpcoDocuments extends Command
{
    protected $signature = 'opco:send-documents';

    protected $description = 'Envoie facture et certificats OPCO-EP au manager, un mois après la signature';

    public function handle(OpcoInvoiceService $opco): int
    {
        $result = $opco->sendDue();

        $this->info(count($result['sent']) . ' envoyé(s), '
            . count($result['waiting']) . ' en attente du certificat, '
            . count($result['failed']) . ' échec(s).');

        return $result['failed'] ? Command::FAILURE : Command::SUCCESS;
    }
}
