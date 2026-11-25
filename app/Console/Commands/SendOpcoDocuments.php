<?php

namespace App\Console\Commands;

use App\Services\Opco\OpcoInvoiceService;
use Illuminate\Console\Command;

/** Fin de formation : chaque apprenant reçoit son certificat de réalisation (date d'envoi arrivée). Pas de facture. */
class SendOpcoDocuments extends Command
{
    protected $signature = 'opco:send-documents';

    protected $description = 'Envoie à chaque apprenant OPCO-EP son certificat de réalisation, un mois après la signature';

    public function handle(OpcoInvoiceService $opco): int
    {
        $result = $opco->sendDue();

        $this->info(count($result['sent']) . ' envoyé(s), '
            . count($result['waiting']) . ' en attente du certificat, '
            . count($result['failed']) . ' échec(s).');

        return $result['failed'] ? Command::FAILURE : Command::SUCCESS;
    }
}
