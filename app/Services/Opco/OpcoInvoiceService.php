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
 * - Traitement complet : opco:process (processAll) — pour chaque convention : facture si elle manque ;
 *   formation terminée (signature + 1 mois atteint) → attestation (PDF de tous les certificats, S3) puis
 *   envoi à chaque apprenant de SA page ; formation en cours → facture seulement (datée signature + 1 mois),
 *   attestation et e-mails au prochain passage après la fin. CMS : mêmes étapes une par une (admin).
 * - invoices.sent_at = date d'envoi prévue, puis date du dernier envoi ; sent_cnt = nombre d'envois.
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
     * Traitement complet des conventions OPCO-EP (commande opco:process), convention par convention :
     *  1. pas de facture → facture Odoo + PDF (date = signature + 1 mois, ou aujourd'hui si déjà passée) ;
     *  2. formation pas terminée (signature + 1 mois dans le futur) → on s'arrête là ;
     *  3. pas d'attestation → attestation (PDF de tous les certificats, S3) ;
     *  4. certificats jamais envoyés (sent_cnt = 0) → chaque apprenant reçoit SA page de l'attestation.
     * Rejouable : ce qui est fait n'est pas refait. Une erreur sur une convention n'arrête pas les autres.
     *
     * @param  bool      $dryRun     ne rien faire, seulement dire ce qui serait fait
     * @param  int[]     $only       seulement ces conventions (id) ; vide = toutes
     * @param  int[]     $exclude    conventions à laisser de côté (id), p. ex. déjà facturées à la main
     * @param  bool      $sendEmails false : facture + attestation, sans e-mail
     * @param  callable|null $report fn(array $row) appelée après chaque convention
     * @param  string|null $since    seulement les conventions signées à partir de cette date (Y-m-d)
     * @return array<int, array{contract: string, company: string, signed: string, end: string, steps: string[], status: string, error: ?string}>
     */
    public function processAll(bool $dryRun = true, array $only = [], array $exclude = [], bool $sendEmails = true, ?callable $report = null, ?string $since = null): array
    {
        $lock = Cache::lock('opco-process', 3600);
        if (!$dryRun && !$lock->get()) {
            throw new RuntimeException('Un traitement OPCO-EP est déjà en cours.');
        }

        try {
            $contracts = Contract::where('organization', self::ORGANIZATION)
                ->where('type', 'signed')
                ->where('status', Status::ACTIVE->value)
                ->when($only, fn ($q) => $q->whereIn('id', $only))
                ->when($exclude, fn ($q) => $q->whereNotIn('id', $exclude))
                ->when($since, fn ($q) => $q->where('created_at', '>=', Carbon::parse($since)->startOfDay()))
                ->orderBy('id')
                ->get();
            $companies = Company::whereIn('id', $contracts->pluck('company_id')->filter()->unique())->pluck('name', 'id');

            $rows = [];
            foreach ($contracts as $contract) {
                $row = $this->processContract($contract, $dryRun, $sendEmails);
                $row['company'] = $companies[$contract->company_id] ?? '—';
                $rows[] = $row;
                if ($report) {
                    $report($row);
                }
            }

            Log::info('OPCO : traitement ' . ($dryRun ? '(simulation)' : ''), [
                'total'  => count($rows),
                'errors' => collect($rows)->where('status', 'erreur')->pluck('contract')->all(),
            ]);

            return $rows;
        } finally {
            if (!$dryRun) {
                $lock->release();
            }
        }
    }

    /** Une convention : voir processAll(). */
    private function processContract(Contract $contract, bool $dryRun, bool $sendEmails): array
    {
        $signedAt = $this->signedAt($contract);
        $end = $signedAt->copy()->addMonth()->startOfDay();
        $row = [
            'contract' => $contract->no,
            'company'  => '',
            'signed'   => $signedAt->format('d/m/Y'),
            'end'      => $end->format('d/m/Y'),
            'steps'    => [],
            'status'   => 'ok',
            'error'    => null,
        ];

        try {
            // 1. Facture
            $invoice = $contract->invoice_id ? Invoice::find($contract->invoice_id) : null;
            if (!$invoice) {
                $date = $this->invoiceDate($contract, now())->format('d/m/Y');
                if ($dryRun) {
                    $this->dossier($contract); // vérifie que la facture peut être faite (panier, apprenants, découpage)
                    $row['steps'][] = "facture à créer (datée du {$date})";
                } else {
                    $invoice = $this->issueForContract($contract);
                    $row['steps'][] = "facture {$invoice->no} créée (datée du {$date})";
                }
            } else {
                $row['steps'][] = "facture {$invoice->no} existante";
            }

            // 2. Formation en cours : rien d'autre avant la fin
            if ($end->isFuture()) {
                $row['steps'][] = "formation en cours : attestation et e-mails à partir du {$row['end']}";
                $row['status'] = 'en cours';
                return $row;
            }

            // 3. Attestation
            if (!$invoice || !$invoice->certification) {
                if ($dryRun) {
                    $row['steps'][] = 'attestation à créer';
                } else {
                    $this->createAttestation($invoice);
                    $invoice->refresh();
                    $row['steps'][] = 'attestation créée';
                }
            } else {
                $row['steps'][] = 'attestation existante';
            }

            // 4. Certificats aux apprenants (une seule fois)
            if ($invoice && (int) $invoice->sent_cnt > 0) {
                $row['steps'][] = 'certificats déjà envoyés';
            } elseif (!$sendEmails) {
                $row['steps'][] = 'e-mails non envoyés (--no-email)';
            } elseif ($dryRun) {
                $learners = $this->dossier($contract)['members'];
                $noEmail = $learners->filter(fn ($l) => !$l->email)->map(fn ($l) => trim($l->first_name . ' ' . $l->name));
                $row['steps'][] = 'certificats à envoyer à ' . ($learners->count() - $noEmail->count()) . ' apprenant(s)'
                    . ($noEmail->isNotEmpty() ? ' — sans e-mail : ' . $noEmail->implode(', ') : '');
            } else {
                $sent = $this->sendCertificates($invoice);
                $row['steps'][] = "certificats envoyés à {$sent['sent']} apprenant(s)"
                    . ($sent['no_email'] ? ' — sans e-mail : ' . implode(', ', $sent['no_email']) : '')
                    . ($sent['failed'] ? ' — échec : ' . implode(', ', $sent['failed']) : '');
                if ($sent['failed']) {
                    $row['status'] = 'à vérifier';
                }
            }
        } catch (Throwable $e) {
            $row['status'] = 'erreur';
            $row['error'] = $e->getMessage();
            Log::error('OPCO : ' . $contract->no . ' — ' . $e->getMessage(), ['opco-process', 'contract-' . $contract->id]);
        }

        return $row;
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
     * $learnerId : renvoyer seulement à cet apprenant (sinon à tous).
     *
     * @return array{sent: int, no_email: string[], failed: string[]}
     */
    public function sendCertificates(Invoice $invoice, ?int $learnerId = null): array
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
        if ($learnerId && !$learners->contains('id', $learnerId)) {
            throw new RuntimeException("Cet apprenant ne fait pas partie de la convention {$contract->no}.");
        }

        foreach ($learners->values() as $i => $learner) {
            if ($learnerId && $learner->id !== $learnerId) {
                continue;
            }
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
                // OPCO : sent_at = date du dernier envoi aux apprenants (avant : date d'envoi prévue)
                'sent_at'  => now(),
                'sent_cnt' => (int) $invoice->sent_cnt + 1,
            ]);
        }

        return $result;
    }

    /**
     * Apprenants des factures OPCO de la page (pour « Renvoyer à … ») : [invoice_id => [[id, name, email], …]],
     * dans l'ordre de l'attestation. Requête légère : apprenants du panier de chaque convention.
     */
    public function learnersByInvoice(array $invoiceIds): array
    {
        if (!$invoiceIds) {
            return [];
        }

        $contracts = Contract::whereIn('invoice_id', $invoiceIds)
            ->where('organization', self::ORGANIZATION)
            ->where('type', 'signed')
            ->get(['invoice_id', 'cart_id']);
        $carts = Cart::with('members:users.id,users.name,users.first_name,users.email')->whereIn('id', $contracts->pluck('cart_id'))->get()->keyBy('id');

        $out = [];
        foreach ($contracts as $c) {
            $out[$c->invoice_id] = ($carts[$c->cart_id]->members ?? collect())
                ->sortBy('id')
                ->map(fn ($m) => ['id' => $m->id, 'name' => trim($m->first_name . ' ' . $m->name), 'email' => $m->email])
                ->values()
                ->all();
        }

        return $out;
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
