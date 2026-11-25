<?php

namespace App\Services\Invoices;

use App\Http\Controllers\GeneratePdfController;
use App\Mail\OpcoCompletedDocumentMail;
use App\Mail\SendFifplCompletedDocumentMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FundingOrganization;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\User;
use App\Organization;
use App\Services\Opco\OpcoInvoiceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Envoi test (CMS) : refait la facture, le certificat et le programme d'une facture existante
 * avec les modèles actuels, et envoie l'e-mail de fin de formation tel qu'il partirait au client,
 * mais à l'adresse saisie.
 *
 * Rien n'est enregistré : ni base (tout se passe dans une transaction annulée), ni S3, ni Odoo
 * (Odoo n'est que lu, pour l'adresse de l'OPCO). Les PDF sont joints directement à l'e-mail.
 */
class InvoiceTestSendService
{
    public const ORGANIZATIONS = ['FIFPL', OpcoInvoiceService::ORGANIZATION];

    public function __construct(
        private OpcoInvoiceService $opco,
        private GeneratePdfController $pdf,
    ) {}

    /** Factures qu'on peut tester : celles qui ont une convention FIFPL ou OPCO-EP (les plus récentes d'abord). */
    public function candidates(int $limit = 500): Collection
    {
        return DB::table('invoices as i')
            ->join('contracts as c', function ($join) {
                $join->on('c.invoice_id', '=', 'i.id')->where('c.type', '=', 'signed');
            })
            ->leftJoin('users as u', 'u.id', '=', 'i.user_id')
            ->whereIn('c.organization', self::ORGANIZATIONS)
            ->orderByDesc('i.id')
            ->limit($limit)
            ->get([
                'i.id',
                'i.no',
                'i.client_name',
                'u.name as user_name',
                'u.first_name as user_first_name',
                'c.no as contract_no',
                'c.organization',
            ]);
    }

    /**
     * @return array{organization: string, invoice_no: string, attachments: string[], notes: string[]}
     */
    public function send(int $invoiceId, string $email): array
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $contract = Contract::where('invoice_id', $invoice->id)
            ->where('type', 'signed')
            ->whereIn('organization', self::ORGANIZATIONS)
            ->first();
        if (!$contract) {
            throw new RuntimeException("La facture {$invoice->no} n'a pas de convention FIFPL ou OPCO-EP : rien à tester.");
        }

        // Filet de sécurité : rien de ce qui suit ne doit écrire en base ; si quelque chose le faisait, c'est annulé.
        DB::beginTransaction();
        try {
            [$mail, $files, $notes] = $contract->organization === OpcoInvoiceService::ORGANIZATION
                ? $this->opcoDocuments($invoice, $contract)
                : $this->fifplDocuments($invoice, $contract);

            foreach ($files as $name => $content) {
                $mail->attachData($content, $name, ['mime' => 'application/pdf']);
            }

            Mail::to($email)->send($mail);
        } finally {
            DB::rollBack();
        }

        return [
            'organization' => $contract->organization,
            'invoice_no'   => $invoice->no,
            'attachments'  => array_keys($files),
            'notes'        => $notes,
        ];
    }

    /* ---------------------------------------------------------------- devis OPCO fictif */

    /** Formations proposées pour le devis fictif (actives, avec une durée). */
    public function packages(): Collection
    {
        return Package::active()->where('duration', '>', 0)->orderBy('name')->get(['id', 'name', 'duration']);
    }

    /**
     * Devis OPCO-EP fictif, tout en mémoire (rien en base, rien sur S3, rien dans Odoo) : entreprise et
     * apprenants inventés, formations réelles ; un seul dossier (≤ 10 h), signé aujourd'hui.
     * Envoie à $email l'e-mail de fin de formation OPCO avec devis + facture + certificat + programme.
     *
     * @param  int[]  $packageIds  vide : formations prises au hasard
     * @return array{organization: string, invoice_no: string, attachments: string[], notes: string[]}
     */
    public function sendFakeOpco(string $email, User $manager, array $packageIds = [], int $learners = 3): array
    {
        $notes = [];
        $maxMinutes = Organization::OPCO_MAX_DURATION->value;

        $pool = Package::active()
            ->with(['modules', 'modules.lessons'])
            ->where('duration', '>', 0)
            ->when($packageIds, fn ($q) => $q->whereIn('id', $packageIds), fn ($q) => $q->inRandomOrder()->limit(20))
            ->get();

        // Un seul dossier : on garde les formations tant que le total reste ≤ 10 h.
        $packages = collect();
        foreach ($pool as $package) {
            if ($packages->sum('duration') + $package->duration <= $maxMinutes) {
                $packages->push($package);
            }
        }
        if ($packages->isEmpty()) {
            throw new RuntimeException('Aucune formation active de 10 h ou moins à mettre dans le devis.');
        }
        if ($packageIds && $packages->count() < count($packageIds)) {
            $notes[] = 'Formations au-delà de 10 h au total laissées de côté (un dossier OPCO = 10 h au plus).';
        }

        // Tout est construit sans être enregistré.
        $cart = (new Cart())->forceFill(['id' => 0, 'payment_type' => 1, 'tva' => (float) setting('system.tva')]);
        $items = $packages->map(fn (Package $p) => (new CartItem())->forceFill(['package_id' => $p->id])->setRelation('package', $p))->values();
        $members = collect(range(1, max(1, min(20, $learners))))->map(fn (int $i) => (new User())->forceFill([
            'name'       => 'TEST',
            'first_name' => 'Apprenant ' . $i,
            'email'      => 'apprenant' . $i . '@example.com',
        ]));
        $company = (new Company())->forceFill([
            'name'        => 'Cabinet TEST OrthoMaster',
            'address'     => '1 rue du Test',
            'postal_code' => '67000',
            'city'        => 'Strasbourg',
            'siret'       => '00000000000000',
        ]);
        $cart->setRelation('items', $items);
        $cart->setRelation('members', $members);

        $code = FundingOrganization::find(1)?->code ?: 'opco-ep';
        $contract = (new Contract())->forceFill([
            'no'           => 'D-' . now()->format('ym') . '-' . $code . '-TEST-000',
            'organization' => OpcoInvoiceService::ORGANIZATION,
            'type'         => 'signed',
            'created_at'   => now(),
        ]);
        $dossier = $this->opco->buildDossier($cart, $items, $members, $manager, $company, (float) $cart->tva);
        $invoice = (new Invoice())->forceFill([
            'no'         => 'TEST/OPCO/' . now()->format('YmdHi'),
            'amount_ht'  => $dossier['amount_ht'],
            'tva'        => $dossier['tva'],
            'tva_amount' => $dossier['tva_amount'],
            'amount'     => $dossier['amount'],
        ]);

        $files = [];
        $today = now();
        $files['Devis_' . $contract->no . '.pdf'] = $this->pdf->quotationTemp(
            0,
            $contract->no,
            $today->format('d/m/Y'),
            $today->copy()->addMonth()->format('d/m/Y'),
            $company,
            $today->format('d/m/Y') . ' - ' . $today->copy()->addMonth()->format('d/m/Y'),
            1,
            $members,
            $items,
            Organization::OPCO_UNIT_PRICE->value,
            (float) $cart->tva,
            $dossier['minutes'],
            null,
            null,
            'content'
        );
        $notes[] = 'Devis non signé (le devis signé porte la signature du manager).';

        $files['Facture_' . str_replace('/', '_', $invoice->no) . '.pdf'] = $this->opco->invoicePdfContent($invoice, $contract, $dossier);

        $certificates = $this->opco->certificatesPdfContent($invoice, $contract, $dossier);
        if ($certificates !== null) {
            $files['Certificats_realisation_' . $contract->no . '.pdf'] = $certificates;
        } else {
            $notes[] = 'Certificat OPCO-EP non créé : non joint.';
        }

        $files['Programme_' . $contract->no . '.pdf'] = $this->pdf->autoProgPdfContentForGroups(
            $cart,
            collect([$items]),
            0,
            $company->name,
            trim($manager->name . ' ' . $manager->first_name),
            $today->format('d/m/Y')
        );

        $mail = new OpcoCompletedDocumentMail($manager, $invoice, $contract);
        foreach ($files as $name => $content) {
            $mail->attachData($content, $name, ['mime' => 'application/pdf']);
        }

        // Filet de sécurité, comme pour une facture existante.
        DB::beginTransaction();
        try {
            Mail::to($email)->send($mail);
        } finally {
            DB::rollBack();
        }

        $notes[] = $packages->count() . ' formation(s), ' . $members->count() . ' apprenant(s), '
            . number_format($dossier['minutes'] / 60, 2, ',', ' ') . ' h, '
            . number_format($dossier['amount_ht'], 2, ',', ' ') . ' € HT.';

        return [
            'organization' => OpcoInvoiceService::ORGANIZATION . ' (fictif)',
            'invoice_no'   => $invoice->no,
            'attachments'  => array_keys($files),
            'notes'        => $notes,
        ];
    }

    /* ---------------------------------------------------------------- FIFPL */

    /** Comme InvoiceController::generateFifplInvoiceDocument() + SendFifplCompletedDocumentMail. */
    private function fifplDocuments(Invoice $invoice, Contract $contract): array
    {
        $cart = Cart::with(['members', 'items', 'items.package'])->findOrFail($contract->cart_id);
        $member = User::findOrFail($contract->beneficiary_id);
        $signedAt = Carbon::parse($contract->getRawOriginal('created_at'));
        $from = $signedAt->copy()->addMonth();
        $to = $signedAt->copy()->addMonths(2);
        $notes = [];

        if (!$invoice->paid_at) {
            $notes[] = 'Facture non réglée : la date de paiement imprimée est celle d\'aujourd\'hui.';
        }

        $files = [];
        $files['Facture_' . str_replace('/', '_', $invoice->no) . '.pdf'] = $this->pdf->fifplPaymentInvoice(
            $invoice->no,
            $contract->no,
            $from->format('d/m/Y'),
            $to->format('d/m/Y'),
            $from->format('d/m/Y') . ' - ' . $to->format('d/m/Y'),
            $contract->company,
            $member,
            $cart->items,
            Organization::FIFPL_UNIT_PRICE->value,
            (float) setting('system.tva'),
            $cart->duration_total_days,
            ($invoice->paid_at ?? now())->format('d/m/Y'),
            'content'
        );

        $files['Certificat_' . $contract->no . '.pdf'] = $this->pdf->fifplCertificateContent(
            $member->name . ' ' . $member->first_name,
            $from,
            $to,
            $cart->items,
            $cart->duration_total,
            sizeof($cart->items),
            $invoice->amount_ht,
            $invoice->amount
        );

        $files['Programme_' . $contract->no . '.pdf'] = $this->programme($contract, null);
        $notes[] = 'Programme refait sans la signature (elle n\'est pas conservée à part).';

        // Même e-mail que le vrai, sans ses pièces jointes d'origine (on joint les PDF refaits).
        $mail = new SendFifplCompletedDocumentMail((string) $invoice->user_id, $this->withoutStoredFiles($invoice));

        return [$mail, $files, $notes];
    }

    /* ---------------------------------------------------------------- OPCO-EP */

    /** Comme OpcoInvoiceService::send(). */
    private function opcoDocuments(Invoice $invoice, Contract $contract): array
    {
        $notes = [];
        $files = [];

        $files['Facture_' . str_replace('/', '_', $invoice->no) . '.pdf'] = $this->opco->invoicePdfContent($invoice, $contract);

        $certificates = $this->opco->certificatesPdfContent($invoice, $contract);
        if ($certificates !== null) {
            $files['Certificats_realisation_' . $contract->no . '.pdf'] = $certificates;
        } else {
            $notes[] = 'Certificat OPCO-EP non créé : non joint.';
        }

        $files['Programme_' . $contract->no . '.pdf'] = $this->programme($contract, $this->opco->dossierIndex($contract));
        $notes[] = 'Programme refait sans la signature (elle n\'est pas conservée à part).';

        $manager = User::findOrFail($invoice->user_id);
        $mail = new OpcoCompletedDocumentMail($manager, $this->withoutStoredFiles($invoice), $contract);

        return [$mail, $files, $notes];
    }

    /* ---------------------------------------------------------------- commun */

    /** Programme du dossier (OPCO) ou de tout le panier (FIFPL), avec entreprise, signataire et date de signature. */
    private function programme(Contract $contract, ?int $groupIndex): string
    {
        $signer = User::find($contract->created_by);
        $company = $contract->company;

        return $this->pdf->autoProgPdfContent(
            $contract->cart_id,
            $groupIndex,
            $company?->name ?? '',
            $signer ? trim($signer->name . ' ' . $signer->first_name) : '',
            Carbon::parse($contract->getRawOriginal('created_at'))->format('d/m/Y')
        );
    }

    /** Copie en mémoire de la facture, sans les PDF déjà enregistrés (jamais sauvegardée). */
    private function withoutStoredFiles(Invoice $invoice): Invoice
    {
        $copy = clone $invoice;
        $copy->document = null;
        $copy->certification = null;

        return $copy;
    }
}
