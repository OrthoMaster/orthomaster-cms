<?php

namespace App\Services\Opco;

use App\Http\Controllers\GeneratePdfController;
use App\Http\Controllers\Odoo\OdooInvoiceController;
use App\Mail\OpcoCompletedDocumentMail;
use App\Models\Cart;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use App\Organization;
use App\Services\OdooService;
use App\Status;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Factures (et certificats de réalisation) des conventions OPCO-EP.
 *
 * Une convention signée (contracts.type = signed, organization = OPCO-EP) = un dossier OPCO
 * = une facture. Le panier est découpé en dossiers de 10 h au plus à la signature
 * (Cart::groupItemsByDurationBestFit) ; le numéro de la convention porte « _n » s'il y en a plusieurs.
 *
 * - Facture Odoo (numéro donné par Odoo, comme FIFPL), client = OPCO EP (res.partner 20) :
 *   l'OPCO règle directement OrthoMaster (subrogation). L'entreprise et les apprenants figurent sur la facture.
 * - Date de facture = signature + 1 mois (fin de formation), échéance = signature + 2 mois.
 * - Créée dès que possible (tâche du soir ou bouton CMS) ; e-mail au manager seulement à la date de facture
 *   (invoices.sent_at = date prévue d'envoi, remise à null une fois envoyé, sent_cnt + 1).
 * - Conventions anciennes (date de facture déjà passée) : documents créés, pas d'e-mail automatique ;
 *   envoi possible depuis la liste des factures du CMS.
 * - Pas de règlement dans le CMS : ni Stripe, ni virement, ni paiement Odoo — seulement facture + certificat.
 */
class OpcoInvoiceService
{
    public const ORGANIZATION = 'OPCO-EP';

    /** Client Odoo des factures : OPCO EP. */
    public const ODOO_PARTNER_ID = 20;

    /** TVA 20 % dans Odoo. */
    public const ODOO_TAX_ID = 3;

    /**
     * Modèle PDF du certificat de réalisation, sur le serveur (storage/app n'est pas versionné),
     * et emplacement des champs à remplir. Tant que l'un des deux manque, aucun certificat n'est
     * créé et l'e-mail de fin de formation attend.
     */
    public const CERTIFICATE_TEMPLATE = 'app/opco/certificate.pdf';
    public const CERTIFICATE_LAYOUT = [];

    public function __construct(private OdooService $odoo) {}

    /* ===================================================================
     * Conventions sans facture
     * =================================================================== */

    /**
     * Conventions OPCO-EP signées, actives (pas désactivées dans le CMS), qui n'ont pas encore de facture.
     * $recentOnly : seulement celles dont la fin de formation (signature + 1 mois) n'est pas encore passée.
     */
    public function contractsWithoutInvoice(bool $recentOnly = false): Collection
    {
        return Contract::where('organization', self::ORGANIZATION)
            ->where('type', 'signed')
            ->where('status', Status::ACTIVE->value)
            ->whereNull('invoice_id')
            ->when($recentOnly, fn ($q) => $q->where('created_at', '>=', today()->subMonth()))
            ->orderBy('id')
            ->get();
    }

    /**
     * Crée la facture (et le certificat si le modèle est prêt) de chaque convention qui n'en a pas.
     * Un seul passage à la fois (tâche du soir et bouton CMS peuvent se croiser).
     *
     * $all = false (tâche du soir) : conventions récentes seulement (fin de formation pas encore passée).
     * $all = true (bouton CMS, opco:invoices --all) : rattrapage de toutes les anciennes conventions,
     * lancé à la main — vérifier avant dans Odoo qu'aucune facture n'y a été faite à la main pour elles.
     *
     * @return array{created: string[], failed: string[], locked: bool}
     */
    public function generateMissing(bool $all = false): array
    {
        $result = ['created' => [], 'failed' => [], 'locked' => false];

        $lock = Cache::lock('opco-invoices-generate', 1800);
        if (!$lock->get()) {
            $result['locked'] = true;
            return $result;
        }

        try {
            foreach ($this->contractsWithoutInvoice(!$all) as $contract) {
                try {
                    $invoice = $this->issueForContract($contract);
                    $result['created'][] = $invoice->no;
                } catch (Throwable $e) {
                    $result['failed'][] = $contract->no;
                    Log::error('OPCO : facture non créée — ' . $e->getMessage(), ['opco-invoice', 'contract-' . $contract->id]);
                }
            }

            // Factures créées dont le PDF a échoué (Odoo a répondu, S3 non) : on le refait.
            Invoice::whereNull('document')
                ->whereIn('id', $this->opcoInvoiceIds())
                ->get()
                ->each(function (Invoice $invoice) use (&$result) {
                    try {
                        $this->renderInvoice($invoice, $this->contractOf($invoice));
                        $this->odooInvoices()->uploadInvoicePdf(
                            $invoice->no,
                            $invoice->document,
                            str_replace('/', '_', $invoice->no) . '.pdf',
                            'Facture OPCO-EP - CMS'
                        );
                    } catch (Throwable $e) {
                        $result['failed'][] = $invoice->no;
                        Log::error('OPCO : PDF non créé — ' . $e->getMessage(), ['opco-invoice', 'inv-' . $invoice->id]);
                    }
                });
        } finally {
            $lock->release();
        }

        Log::info('OPCO : factures créées', $result);
        return $result;
    }

    /**
     * Facture d'une convention : Odoo (numéro) → ligne invoices → PDF → dépôt du PDF dans Odoo
     * → certificat si possible. Rejouable sans doublon : la pièce Odoo est retrouvée par sa référence.
     */
    public function issueForContract(Contract $contract): Invoice
    {
        $dossier = $this->dossier($contract);
        $signedAt = Carbon::parse($contract->getRawOriginal('created_at'));
        $invoiceDate = $signedAt->copy()->addMonth();
        $dueDate = $signedAt->copy()->addMonths(2);

        $invoice = DB::transaction(function () use ($contract, $dossier, $invoiceDate, $dueDate) {
            $locked = Contract::whereKey($contract->id)->lockForUpdate()->first();
            if ($locked->invoice_id && ($existing = Invoice::find($locked->invoice_id))) {
                return $existing;
            }

            $odooInvoice = $this->findOdooInvoice($contract->no) ?: $this->odooInvoices()->createInvoice([
                'partner_id'       => self::ODOO_PARTNER_ID,
                'invoice_date'     => $invoiceDate->format('Y-m-d'),
                'invoice_date_due' => $dueDate->format('Y-m-d'),
                'ref'              => $contract->no,
                'narration'        => $this->narration($dossier),
                'lines'            => [[
                    'name'       => $this->lineLabel($contract, $dossier),
                    'price_unit' => $dossier['amount_ht'],
                    'quantity'   => 1,
                    'tax_ids'    => [[6, 0, [self::ODOO_TAX_ID]]],
                ]],
            ]);

            if (!$odooInvoice || empty($odooInvoice['name'])) {
                throw new RuntimeException("Odoo : facture non créée pour la convention {$contract->no}.");
            }

            $partner = $this->opcoPartner();
            $company = $dossier['company'];

            $invoice = Invoice::create([
                'no'             => $odooInvoice['name'],
                'id_odoo'        => $odooInvoice['id'],
                'user_id'        => $dossier['manager']->id,
                'amount'         => $dossier['amount'],
                'currency'       => 'eur',
                'status'         => Invoice::STATUS_PENDING,
                'unit_price'     => $dossier['unit_price'],
                'tva'            => $dossier['tva'],
                'tva_amount'     => $dossier['tva_amount'],
                'amount_ht'      => $dossier['amount_ht'],
                'issued_at'      => now(),
                // Envoi au manager à la date de facture ; pas d'envoi automatique si elle est déjà passée.
                'sent_at'        => $invoiceDate->isBefore(today()) ? null : $invoiceDate,
                'client_name'    => $partner['name'],
                'client_email'   => $dossier['manager']->email,
                'client_company' => $company?->name,
                'client_address' => $company ? trim($company->address . ', ' . $company->postal_code . ' ' . $company->city, ' ,') : null,
            ]);

            // Sans toucher updated_at : le tableau de bord commercial compte les conventions par updated_at.
            $locked->timestamps = false;
            $locked->invoice_id = $invoice->id;
            $locked->save();

            return $invoice;
        });

        $this->renderInvoice($invoice, $contract);
        $this->odooInvoices()->uploadInvoicePdf(
            $invoice->no,
            $invoice->document,
            str_replace('/', '_', $invoice->no) . '.pdf',
            'Facture OPCO-EP - CMS'
        );

        $this->generateCertificates($invoice, $contract);

        Log::info('OPCO : facture ' . $invoice->no, ['opco-invoice', 'contract-' . $contract->id]);
        return $invoice;
    }

    /* ===================================================================
     * E-mail de fin de formation au manager
     * =================================================================== */

    /** Factures OPCO dont la date d'envoi est arrivée (rattrape aussi un jour manqué). */
    public function dueForSending(): Collection
    {
        return Invoice::whereNotNull('sent_at')
            ->whereDate('sent_at', '<=', today())
            ->whereIn('id', $this->opcoInvoiceIds())
            ->orderBy('id')
            ->get();
    }

    /** @return array{sent: string[], waiting: string[], failed: string[]} */
    public function sendDue(): array
    {
        $result = ['sent' => [], 'waiting' => [], 'failed' => []];

        foreach ($this->dueForSending() as $invoice) {
            try {
                if ($this->send($invoice)) {
                    $result['sent'][] = $invoice->no;
                } else {
                    $result['waiting'][] = $invoice->no;
                }
            } catch (Throwable $e) {
                $result['failed'][] = $invoice->no;
                Log::error('OPCO : e-mail non envoyé — ' . $e->getMessage(), ['opco-invoice', 'inv-' . $invoice->id]);
            }
        }

        Log::info('OPCO : envoi des documents', $result);
        return $result;
    }

    /**
     * Envoie facture + certificats au manager. Attend le certificat : false s'il ne peut pas encore
     * être créé (modèle absent), l'envoi reste alors prévu.
     */
    public function send(Invoice $invoice): bool
    {
        $contract = $this->contractOf($invoice);
        if (!$contract) {
            throw new RuntimeException("Facture {$invoice->no} : convention OPCO introuvable.");
        }

        if (!$invoice->document) {
            $this->renderInvoice($invoice, $contract);
        }
        if (!$invoice->certification && !$this->generateCertificates($invoice, $contract)) {
            Log::warning('OPCO : e-mail en attente du certificat de réalisation', ['opco-invoice', 'inv-' . $invoice->id]);
            return false;
        }

        $manager = User::findOrFail($invoice->user_id);
        Mail::to($manager->email)->send(new OpcoCompletedDocumentMail($manager, $invoice->fresh(), $contract));

        $invoice->update([
            'sent_at'  => null,
            'sent_cnt' => (int) $invoice->sent_cnt + 1,
        ]);

        return true;
    }

    /* ===================================================================
     * Certificat de réalisation
     * =================================================================== */

    public function certificateReady(): bool
    {
        return !empty(self::CERTIFICATE_LAYOUT) && is_file(storage_path(self::CERTIFICATE_TEMPLATE));
    }

    /**
     * Un PDF par convention, une page par apprenant, dans invoices.certification.
     * Retourne l'URL, ou null tant que le modèle n'est pas prêt.
     */
    public function generateCertificates(Invoice $invoice, Contract $contract): ?string
    {
        $content = $this->certificatesPdfContent($invoice, $contract);
        if ($content === null) {
            return null;
        }

        $fileName = 'certifications/opco/' . str_replace('/', '_', $invoice->no) . '.pdf';
        if (!Storage::disk('s3')->put($fileName, $content)) {
            throw new RuntimeException("Facture {$invoice->no} : certificats non enregistrés.");
        }

        $invoice->certification = Storage::disk('s3')->url($fileName);
        $invoice->save();

        return $invoice->certification;
    }

    /** Contenu du PDF des certificats (une page par apprenant), sans rien enregistrer ; null tant que le modèle manque. */
    public function certificatesPdfContent(Invoice $invoice, Contract $contract): ?string
    {
        if (!$this->certificateReady()) {
            return null;
        }

        // À écrire avec le modèle PDF : remplissage FPDI comme GeneratePdfController::fifplCertificateContent(),
        // une page par apprenant du dossier ($this->dossier($contract)['members']).
        return null;
    }

    /* ===================================================================
     * Dossier d'une convention
     * =================================================================== */

    /**
     * Ce que couvre la convention : son groupe de formations (même découpage qu'à la signature),
     * les apprenants, le manager, l'entreprise et les montants (20 € HT / h / apprenant, comme le devis).
     */
    public function dossier(Contract $contract): array
    {
        $cart = Cart::with(['members', 'items', 'items.package'])->find($contract->cart_id);
        if (!$cart) {
            throw new RuntimeException("Convention {$contract->no} : panier #{$contract->cart_id} introuvable.");
        }

        $groups = $cart->groupItemsByDurationBestFit(Organization::OPCO_MAX_DURATION->value);

        // Le découpage refait aujourd'hui doit retomber sur celui de la signature (une convention par dossier) ;
        // sinon (durée d'une formation modifiée depuis…), on ne devine pas : facture à faire à la main.
        $signedCount = Contract::where('cart_id', $cart->id)
            ->where('organization', self::ORGANIZATION)
            ->where('type', 'signed')
            ->count();
        if ($signedCount !== $groups->count()) {
            throw new RuntimeException("Convention {$contract->no} : {$signedCount} convention(s) signée(s) mais {$groups->count()} dossier(s) recalculé(s) pour le panier #{$cart->id}.");
        }

        $index = $this->dossierIndex($contract);
        $items = $groups->get($index);
        if (!$items || $items->isEmpty()) {
            throw new RuntimeException("Convention {$contract->no} : dossier n° " . ($index + 1) . " introuvable dans le panier #{$cart->id}.");
        }

        $members = $cart->members->values();
        if ($members->isEmpty()) {
            throw new RuntimeException("Convention {$contract->no} : aucun apprenant.");
        }

        $manager = User::find($contract->beneficiary_id ?: $contract->created_by);
        if (!$manager) {
            throw new RuntimeException("Convention {$contract->no} : manager introuvable.");
        }

        $minutes = (int) $cart->calculateGroupDuration($items);
        $unitPrice = (float) Organization::OPCO_UNIT_PRICE->value;
        $tva = (float) ($cart->tva ?? setting('system.tva'));
        $amountHt = round($unitPrice * $minutes / 60 * $members->count(), 2);
        $tvaAmount = round($amountHt * $tva / 100, 2);

        return [
            'cart'       => $cart,
            'items'      => $items->values(),
            'members'    => $members,
            'manager'    => $manager,
            'company'    => $contract->company_id ? Company::find($contract->company_id) : null,
            'minutes'    => $minutes,
            'unit_price' => $unitPrice,
            'tva'        => $tva,
            'amount_ht'  => $amountHt,
            'tva_amount' => $tvaAmount,
            'amount'     => round($amountHt + $tvaAmount, 2),
        ];
    }

    /** Rang du dossier dans le panier (0 = premier) : suffixe « _n » du numéro de convention. */
    public function dossierIndex(Contract $contract): int
    {
        return preg_match('/_(\d+)$/', (string) $contract->no, $m) ? ((int) $m[1]) - 1 : 0;
    }

    /** Convention OPCO-EP d'une facture, ou null si la facture n'est pas une facture OPCO. */
    public function contractOf(Invoice $invoice): ?Contract
    {
        return Contract::where('invoice_id', $invoice->id)
            ->where('organization', self::ORGANIZATION)
            ->where('type', 'signed')
            ->first();
    }

    public function isOpcoInvoice(int $invoiceId): bool
    {
        return Contract::where('invoice_id', $invoiceId)
            ->where('organization', self::ORGANIZATION)
            ->where('type', 'signed')
            ->exists();
    }

    private function opcoInvoiceIds()
    {
        return Contract::where('organization', self::ORGANIZATION)
            ->where('type', 'signed')
            ->whereNotNull('invoice_id')
            ->select('invoice_id');
    }

    /* ===================================================================
     * PDF de la facture
     * =================================================================== */

    /** (Re)crée le PDF de la facture et l'enregistre sur S3 (invoices.document). */
    public function renderInvoice(Invoice $invoice, Contract $contract): string
    {
        $fileName = 'factures/opco/' . str_replace('/', '_', $invoice->no) . '.pdf';
        if (!Storage::disk('s3')->put($fileName, $this->invoicePdfContent($invoice, $contract))) {
            throw new RuntimeException("Facture {$invoice->no} : PDF non enregistré.");
        }

        $invoice->document = Storage::disk('s3')->url($fileName);
        $invoice->save();

        return $invoice->document;
    }

    /** Contenu du PDF de la facture, sans rien enregistrer (aussi pour l'envoi test du CMS). */
    public function invoicePdfContent(Invoice $invoice, Contract $contract): string
    {
        $dossier = $this->dossier($contract);
        $signedAt = Carbon::parse($contract->getRawOriginal('created_at'));

        $pdf = Pdf::loadView('pdf.opco_invoice', [
            'company'    => GeneratePdfController::COMPANY,
            'opco'       => $this->opcoPartner(),
            'client'     => $dossier['company'],
            'invNo'      => $invoice->no,
            'qNo'        => $contract->no,
            'date'       => $signedAt->copy()->addMonth()->format('d/m/Y'),
            'exp_date'   => $signedAt->copy()->addMonths(2)->format('d/m/Y'),
            'rangeDate'  => $signedAt->format('d/m/Y') . ' - ' . $signedAt->copy()->addMonth()->format('d/m/Y'),
            'members'    => $dossier['members'],
            'items'      => $dossier['items'],
            'unitPrice'  => $dossier['unit_price'],
            'minutes'    => $dossier['minutes'],
            'amount_ht'  => (float) $invoice->amount_ht,
            'tva'        => $dossier['tva'],
            'tva_amount' => (float) $invoice->tva_amount,
            'amount'     => (float) $invoice->amount,
        ]);
        $pdf->setOption('isPhpEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    /* ===================================================================
     * Odoo
     * =================================================================== */

    private function odooInvoices(): OdooInvoiceController
    {
        return new OdooInvoiceController($this->odoo);
    }

    /** Pièce déjà créée lors d'un essai interrompu : on la reprend au lieu d'en créer une autre. */
    private function findOdooInvoice(string $ref): ?array
    {
        $found = $this->odoo->searchRead('account.move', [
            ['ref', '=', $ref],
            ['partner_id', '=', self::ODOO_PARTNER_ID],
            ['move_type', '=', 'out_invoice'],
            ['state', '=', 'posted'],
        ], ['id', 'name'], 1);

        return $found[0] ?? null;
    }

    /** Nom et adresse de l'OPCO tels qu'enregistrés dans Odoo (gardés un jour ; pas gardés si Odoo ne répond pas). */
    public function opcoPartner(): array
    {
        $key = 'opco-odoo-partner-' . self::ODOO_PARTNER_ID;
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $p = $this->odoo->searchRead('res.partner', [['id', '=', self::ODOO_PARTNER_ID]], ['name', 'street', 'street2', 'zip', 'city', 'vat'], 1)[0] ?? null;
        } catch (Throwable $e) {
            Log::warning('OPCO : partenaire Odoo illisible — ' . $e->getMessage(), ['opco-invoice']);
            $p = null;
        }

        // Odoo renvoie false pour un champ vide.
        $field = fn (string $name) => (is_array($p) && !empty($p[$name])) ? (string) $p[$name] : null;
        $partner = [
            'name'   => $field('name') ?? 'OPCO EP',
            'street' => trim(($field('street') ?? '') . ' ' . ($field('street2') ?? '')) ?: null,
            'zip'    => $field('zip'),
            'city'   => $field('city'),
            'vat'    => $field('vat'),
        ];

        if ($p) {
            Cache::put($key, $partner, 86400);
        }

        return $partner;
    }

    private function lineLabel(Contract $contract, array $dossier): string
    {
        $hours = rtrim(rtrim(number_format($dossier['minutes'] / 60, 2, ',', ''), '0'), ',');
        $n = $dossier['members']->count();

        return 'Formation OrthoMaster — convention ' . $contract->no
            . ' : ' . $dossier['items']->map(fn ($i) => $i->package->name)->implode(', ')
            . " ({$hours} h × {$n} apprenant" . ($n > 1 ? 's' : '') . ')';
    }

    private function narration(array $dossier): string
    {
        $company = $dossier['company'];

        return 'Entreprise : ' . ($company?->name ?? '—')
            . ($company?->siret ? ' (SIRET ' . $company->siret . ')' : '')
            . ' — Apprenants : ' . $dossier['members']->map(fn ($m) => trim($m->name . ' ' . $m->first_name))->implode(', ');
    }
}
