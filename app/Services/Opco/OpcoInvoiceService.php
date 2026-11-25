<?php

namespace App\Services\Opco;

use App\Http\Controllers\GeneratePdfController;
use App\Http\Controllers\Odoo\OdooInvoiceController;
use App\Mail\OpcoCertificateMail;
use App\Models\Cart;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use App\Organization;
use App\Services\OdooService;
use App\Status;
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Tcpdf\Fpdi;
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
 * - Date de facture = signature + 1 mois (fin de formation) ; convention rattrapée après cette date :
 *   date du jour de création. Échéance = date de facture.
 * - Créée dès que possible (tâche du soir ou bouton CMS) ; à la fin de la formation, chaque apprenant reçoit
 *   par e-mail SON certificat de réalisation (la facture n'est envoyée à personne)
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

    /** Client imprimé sur la facture (en-tête gauche). */
    public const OPCO_CLIENT = [
        'name'    => 'OPCO des Entreprises de Proximité',
        'address' => ['4 rue Colonel Driant', '75046 Paris Cedex 1', 'France'],
        'vat'     => 'FR21879036895',
    ];

    /** TVA 20 % dans Odoo. */
    public const ODOO_TAX_ID = 3;

    /** Certificat de réalisation : signataire pour l'organisme, lieu de signature, logos. */
    public const CERTIFICATE_SIGNATORY = 'LÊ Guillaume';
    public const CERTIFICATE_PLACE = 'Strasbourg';
    public const CERTIFICATE_LOGO = 'https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/logo-v2/pdf/logo_primary_1024.png';
    public const CERTIFICATE_BRAND = 'https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/logo-v2/pdf/brand_name_primary.png';

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
        $signedAt = $this->signedAt($contract);
        $plannedDate = $signedAt->copy()->addMonth()->startOfDay();
        $invoiceDate = $this->invoiceDate($contract, now());
        $dueDate = $invoiceDate;

        $invoice = DB::transaction(function () use ($contract, $dossier, $plannedDate, $invoiceDate, $dueDate) {
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
                // Envoi des certificats aux apprenants à la fin de formation ; convention rattrapée (date prévue passée) : pas d'envoi automatique.
                'sent_at'        => $plannedDate->isBefore(today()) ? null : $plannedDate,
                'client_name'    => self::OPCO_CLIENT['name'],
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

        // L'attestation (certificats) se fait à part : bouton du CMS ou envoi de fin de formation.

        Log::info('OPCO : facture ' . $invoice->no, ['opco-invoice', 'contract-' . $contract->id]);
        return $invoice;
    }

    /* ===================================================================
     * E-mail de fin de formation : certificat de réalisation à chaque apprenant
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
     * Fin de formation (tâche opco:send-documents, désactivée pour l'instant) : attestation si elle manque,
     * puis envoi à chaque apprenant de SON certificat. La facture n'est pas envoyée.
     */
    public function send(Invoice $invoice): bool
    {
        $contract = $this->contractOf($invoice);
        if (!$contract) {
            throw new RuntimeException("Facture {$invoice->no} : convention OPCO introuvable.");
        }

        if (!$invoice->certification && !$this->generateCertificates($invoice, $contract)) {
            Log::warning('OPCO : attestation non créée, rien envoyé', ['opco-invoice', 'inv-' . $invoice->id]);
            return false;
        }

        return $this->sendCertificates($invoice)['sent'] > 0;
    }

    /**
     * Bouton « Créer l'attestation » : PDF de tous les certificats (une page par apprenant), sur S3
     * (invoices.certification). Une seule fois : c'est ce document, vérifié, qui sera découpé et envoyé.
     */
    public function createAttestation(Invoice $invoice): string
    {
        $contract = $this->contractOf($invoice);
        if (!$contract) {
            throw new RuntimeException("Facture {$invoice->no} : convention OPCO introuvable.");
        }
        if ($invoice->certification) {
            throw new RuntimeException("L'attestation de la facture {$invoice->no} existe déjà.");
        }

        $url = $this->generateCertificates($invoice, $contract);
        if (!$url) {
            throw new RuntimeException("Facture {$invoice->no} : attestation non créée.");
        }

        return $url;
    }

    /**
     * Bouton « Envoyer les certificats » (après vérification de l'attestation) : chaque apprenant reçoit
     * SA page de l'attestation enregistrée — exactement le document vérifié, rien n'est refait.
     *
     * @return array{sent: int, no_email: string[], failed: string[]}
     */
    public function sendCertificates(Invoice $invoice): array
    {
        $contract = $this->contractOf($invoice);
        if (!$contract) {
            throw new RuntimeException("Facture {$invoice->no} : convention OPCO introuvable.");
        }
        if (!$invoice->certification) {
            throw new RuntimeException("Facture {$invoice->no} : créez d'abord l'attestation.");
        }

        $dossier = $this->dossier($contract);
        $learners = $dossier['members'];
        $pages = $this->attestationPages($invoice);
        if (count($pages) !== $learners->count()) {
            throw new RuntimeException("Attestation de la facture {$invoice->no} : " . count($pages)
                . " page(s) pour {$learners->count()} apprenant(s). Rien n'a été envoyé.");
        }

        $result = ['sent' => 0, 'no_email' => [], 'failed' => []];
        foreach ($learners->values() as $i => $learner) {
            $name = trim($learner->first_name . ' ' . $learner->name);
            if (!$learner->email) {
                $result['no_email'][] = $name;
                Log::warning('OPCO : apprenant sans e-mail, certificat non envoyé', ['opco-invoice', 'inv-' . $invoice->id, 'user-' . $learner->id]);
                continue;
            }
            try {
                $mail = (new OpcoCertificateMail($learner, $contract))
                    ->attachData($pages[$i], $this->certificateFileName($learner), ['mime' => 'application/pdf']);
                Mail::to($learner->email)->send($mail);
                $result['sent']++;
            } catch (Throwable $e) {
                $result['failed'][] = $name;
                Log::error('OPCO : certificat non envoyé — ' . $e->getMessage(), ['opco-invoice', 'inv-' . $invoice->id, 'user-' . $learner->id]);
            }
        }

        if ($result['sent'] > 0) {
            $invoice->update([
                'sent_at'  => null,
                'sent_cnt' => (int) $invoice->sent_cnt + 1,
            ]);
        }

        return $result;
    }

    /** Pages de l'attestation enregistrée, une par apprenant (même ordre que dossier()['members']). */
    private function attestationPages(Invoice $invoice): array
    {
        $content = Storage::disk('s3')->get($this->attestationPath($invoice));
        if (!$content) {
            throw new RuntimeException("Attestation de la facture {$invoice->no} introuvable sur S3.");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'opco-att');
        file_put_contents($tmp, $content);
        try {
            $count = (new Fpdi())->setSourceFile($tmp);
            $pages = [];
            for ($i = 1; $i <= $count; $i++) {
                $one = new Fpdi();
                $one->setPrintHeader(false);
                $one->setPrintFooter(false);
                $one->setSourceFile($tmp);
                $tpl = $one->importPage($i);
                $size = $one->getTemplateSize($tpl);
                $one->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $one->useTemplate($tpl);
                $pages[] = $one->Output('', 'S');
            }

            return $pages;
        } finally {
            @unlink($tmp);
        }
    }

    public function attestationPath(Invoice $invoice): string
    {
        return 'certifications/opco/' . str_replace('/', '_', $invoice->no) . '.pdf';
    }

    public function certificateFileName(User $learner): string
    {
        $name = \Illuminate\Support\Str::slug(trim($learner->first_name . ' ' . $learner->name), '_') ?: 'apprenant';

        return 'Certificat_realisation_' . $name . '.pdf';
    }

    /* ===================================================================
     * Certificat de réalisation
     * =================================================================== */

    /** Un PDF par convention, une page par apprenant, enregistré sur S3 (invoices.certification). Retourne l'URL. */
    public function generateCertificates(Invoice $invoice, Contract $contract): ?string
    {
        $content = $this->certificatesPdfContent($invoice, $contract);
        if ($content === null) {
            return null;
        }

        $fileName = $this->attestationPath($invoice);
        if (!Storage::disk('s3')->put($fileName, $content)) {
            throw new RuntimeException("Facture {$invoice->no} : certificats non enregistrés.");
        }

        $invoice->certification = Storage::disk('s3')->url($fileName);
        $invoice->save();

        return $invoice->certification;
    }

    /**
     * Contenu du PDF des certificats, sans rien enregistrer : une page par apprenant (pdf.opco_certificate).
     * Lieu de travail = entreprise de la convention ; du … au … = période du devis (signature → + 1 mois) ;
     * durée = heures du dossier ; modules = noms longs des formations du dossier ;
     * « Le » = le plus tardif entre fin de formation et jour de création du fichier.
     * $learners : seulement ces apprenants (certificat envoyé à un apprenant) ; sinon tous ceux du dossier.
     */
    public function certificatesPdfContent(Invoice $invoice, Contract $contract, ?array $dossier = null, ?Collection $learners = null): ?string
    {
        $dossier ??= $this->dossier($contract);
        $learners ??= $dossier['members'];
        $signedAt = $this->signedAt($contract);
        $company = $dossier['company'];

        $workplace = $company
            ? collect([
                $company->name,
                $company->address,
                trim($company->postal_code . ' ' . $company->city),
                'France',
            ])->map(fn ($v) => trim((string) $v))->filter()->implode(', ')
            : '—';

        $hours = $dossier['minutes'] / 60;
        $endDate = $signedAt->copy()->addMonth()->startOfDay();
        // Signé au plus tôt le jour de fin de formation : le plus tardif entre fin de formation et création du fichier.
        $signedOn = today()->greaterThan($endDate) ? today() : $endDate;

        $pdf = Pdf::loadView('pdf.opco_certificate', [
            'company'     => GeneratePdfController::COMPANY,
            'logo'        => self::CERTIFICATE_LOGO,
            'brand'       => self::CERTIFICATE_BRAND,
            'signatory'   => self::CERTIFICATE_SIGNATORY,
            'place'       => self::CERTIFICATE_PLACE,
            'learners'    => $learners->map(fn ($m) => trim($m->first_name . ' ' . $m->name))->values(),
            // Nom long de la formation (celui des certificats FIFPL), sinon son nom
            'modules'     => $dossier['items']->map(fn ($i) => $i->package->fifpl_cert_name ?: $i->package->name)->values(),
            'workplace'   => $workplace,
            'from'        => $signedAt->format('d/m/Y'),
            'to'          => $endDate->format('d/m/Y'),
            'hours'       => rtrim(rtrim(number_format($hours, 2, ',', ''), '0'), ','),
            'hoursLabel'  => $hours > 1 ? 'heures' : 'heure',
            'signedOn'    => $signedOn->format('d/m/Y'),
        ]);
        $pdf->setOption('isPhpEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
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

        // Ordre fixe (id) : la page n de l'attestation = le n-ième apprenant.
        $members = $cart->members->sortBy('id')->values();
        if ($members->isEmpty()) {
            throw new RuntimeException("Convention {$contract->no} : aucun apprenant.");
        }

        $manager = User::find($contract->beneficiary_id ?: $contract->created_by);
        if (!$manager) {
            throw new RuntimeException("Convention {$contract->no} : manager introuvable.");
        }

        return $this->buildDossier(
            $cart,
            $items,
            $members,
            $manager,
            $contract->company_id ? Company::find($contract->company_id) : null,
            (float) ($cart->tva ?? setting('system.tva'))
        );
    }

    /**
     * Dossier à partir de ses éléments (aussi des modèles non enregistrés : devis fictif de l'écran de test) :
     * montants comme le devis, 20 € HT / h / apprenant.
     */
    public function buildDossier(Cart $cart, Collection $items, Collection $members, User $manager, ?Company $company, float $tva): array
    {
        $minutes = (int) $cart->calculateGroupDuration($items);
        $unitPrice = (float) Organization::OPCO_UNIT_PRICE->value;
        $amountHt = round($unitPrice * $minutes / 60 * $members->count(), 2);
        $tvaAmount = round($amountHt * $tva / 100, 2);

        return [
            'cart'       => $cart,
            'items'      => $items->values(),
            'members'    => $members->values(),
            'manager'    => $manager,
            'company'    => $company,
            'minutes'    => $minutes,
            'unit_price' => $unitPrice,
            'tva'        => $tva,
            'amount_ht'  => $amountHt,
            'tva_amount' => $tvaAmount,
            'amount'     => round($amountHt + $tvaAmount, 2),
        ];
    }

    /** Date de signature de la convention (now pour une convention non enregistrée sans date). */
    public function signedAt(Contract $contract): Carbon
    {
        $raw = $contract->getRawOriginal('created_at') ?: ($contract->getAttributes()['created_at'] ?? null);

        return $raw ? Carbon::parse($raw) : now();
    }

    /**
     * Date de la facture : signature + 1 mois ; si la facture est créée plus tard (rattrapage d'une
     * convention dépassée), le jour de sa création. $issuedAt = invoices.issued_at (null : aujourd'hui).
     */
    public function invoiceDate(Contract $contract, $issuedAt = null): Carbon
    {
        $planned = $this->signedAt($contract)->copy()->addMonth()->startOfDay();
        $issued = Carbon::parse($issuedAt ?? now())->startOfDay();

        return $issued->greaterThan($planned) ? $issued : $planned;
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

    /**
     * Contenu du PDF de la facture, sans rien enregistrer (aussi pour l'écran de test).
     * $dossier : déjà calculé (devis fictif) ; sinon lu depuis la convention.
     */
    public function invoicePdfContent(Invoice $invoice, Contract $contract, ?array $dossier = null): string
    {
        $dossier ??= $this->dossier($contract);
        $invoiceDate = $this->invoiceDate($contract, $invoice->issued_at);

        $pdf = Pdf::loadView('pdf.opco_invoice', [
            'company'    => GeneratePdfController::COMPANY,
            'opco'       => self::OPCO_CLIENT,
            'invNo'      => $invoice->no,
            'qNo'        => $contract->no,
            'date'       => $invoiceDate->format('d/m/Y'),
            'exp_date'   => $invoiceDate->format('d/m/Y'), // échéance = date de facture
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
