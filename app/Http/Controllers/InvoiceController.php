<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\CartController;
use App\Http\Controllers\Odoo\OdooInvoiceController;
use App\Mail\SendFifplCompletedDocumentMail;
use App\Models\Cart;
use App\Models\Contract;
use App\Models\FundingOrganization;
use App\Models\Invoice;
use App\Models\SystemNotification;
use App\Models\User;
use App\Organization;
use App\Services\OdooService;
use App\Services\Opco\OpcoInvoiceService;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /** Nombre de factures par page */
    private const PER_PAGE = 25;

    /** Statuts possibles d'une facture */
    private const STATUSES = [
        'paid'       => ['label' => 'Payée',      'class' => 'is-paid'],
        'processing' => ['label' => 'En cours',   'class' => 'is-processing'],
        'pending'    => ['label' => 'En attente', 'class' => 'is-pending'],
    ];

    /**
     * Liste des factures.
     * GET /invoices/list
     */
    /** Création de factures OPCO-EP, attestations, envoi des certificats : administrateurs seulement (role_id = 1). */
    public static function canManageOpco(): bool
    {
        return (int) optional(auth()->user())->role_id === 1;
    }

    /** Conventions OPCO-EP sans facture, pour le choix du bouton « Créer la facture OPCO-EP ». */
    private function opcoContractOptions()
    {
        $contracts = app(OpcoInvoiceService::class)->contractsWithoutInvoice();
        $companies = \App\Models\Company::whereIn('id', $contracts->pluck('company_id')->filter()->unique())->pluck('name', 'id');

        return $contracts->sortByDesc('id')->map(fn ($c) => (object) [
            'id'        => $c->id,
            'no'        => $c->no,
            'company'   => $companies[$c->company_id] ?? '—',
            'signed_at' => \Illuminate\Support\Carbon::parse($c->getRawOriginal('created_at'))->format('d/m/Y'),
        ])->values();
    }

    /** Filtre « origine » de la liste : en plus des organismes de financement. */
    private const ORIGIN_BOOKING = 'rendez-vous';
    private const ORIGIN_DIRECT = 'direct';

    public function list(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        if (!array_key_exists($status, self::STATUSES)) {
            $status = null;
        }

        // Organisme / origine : organismes de financement (convention signée), rendez-vous expert, paiement direct
        $origins = FundingOrganization::orderBy('id')->pluck('name')
            ->mapWithKeys(fn ($name) => [$name => $name])
            ->all() + [
                self::ORIGIN_BOOKING => 'Rendez-vous expert',
                self::ORIGIN_DIRECT  => 'Paiement direct (carte)',
            ];
        $origin = $request->query('origin');
        if (!array_key_exists($origin, $origins)) {
            $origin = null;
        }
        $cartInvoice = fn ($q) => $q->select(DB::raw(1))->from('carts as k')->whereColumn('k.invoice_id', 'i.id');

        $invoices = DB::table('invoices as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.user_id')
            // Convention liée (FIFPL / OPCO-EP) : une facture au plus par convention signée.
            ->leftJoin('contracts as c', function ($join) {
                $join->on('c.invoice_id', '=', 'i.id')->where('c.type', '=', 'signed');
            })
            ->select(
                'i.id',
                'i.no',
                'i.user_id',
                'i.amount',
                'i.currency',
                'i.status',
                'i.created_at',
                'i.client_name',
                'i.client_email',
                'i.client_company',
                'i.client_address',
                'i.payment_method',
                'i.stripe_payment_intent_id',
                'i.failed_reason',
                'i.paid_at',
                'i.document',
                'i.certification',
                'i.sent_at',
                'i.sent_cnt',
                'u.email as user_email',
                'i.booking_id',
                'c.no as contract_no',
                'c.organization as contract_organization'
            )
            ->selectRaw('EXISTS (SELECT 1 FROM carts k WHERE k.invoice_id = i.id) as is_direct')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('i.no', 'like', '%' . $search . '%')
                        ->orWhere('i.client_name', 'like', '%' . $search . '%')
                        ->orWhere('i.client_email', 'like', '%' . $search . '%');
                });
            })
            ->when($status, fn($q) => $q->where('i.status', $status))
            ->when($origin === self::ORIGIN_BOOKING, fn ($q) => $q->whereNotNull('i.booking_id'))
            ->when($origin === self::ORIGIN_DIRECT, fn ($q) => $q->whereNull('c.id')->whereNull('i.booking_id')->whereExists($cartInvoice))
            ->when($origin && !in_array($origin, [self::ORIGIN_BOOKING, self::ORIGIN_DIRECT], true), fn ($q) => $q->where('c.organization', $origin))
            ->orderByDesc('i.created_at')
            ->orderByDesc('i.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('invoices.list', [
            'invoices' => $invoices,
            'canManageOpco' => self::canManageOpco(),
            'opcoPending' => self::canManageOpco() ? $this->opcoContractOptions() : collect(),
            'opcoOrganization' => OpcoInvoiceService::ORGANIZATION,
            'opcoLearners' => app(OpcoInvoiceService::class)->learnersByInvoice(
                collect($invoices->items())
                    ->filter(fn ($i) => $i->contract_organization === OpcoInvoiceService::ORGANIZATION && $i->certification)
                    ->pluck('id')->all()
            ),
            'search'   => $search,
            'status'   => $status,
            'statuses' => self::STATUSES,
            'origin'   => $origin,
            'origins'  => $origins,
        ]);
    }

    /**
     * Enregistre un règlement par virement.
     *
     * Le paiement par carte est traité automatiquement ; un virement,
     * lui, ne se constate qu'après coup, à la date du relevé bancaire.
     *
     * POST /invoices/{invoice}/virement
     */
    public function markAsVirement(Request $request, int $invoiceId)
    {
        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();

        abort_if(!$invoice, 404);

        if ($invoice->status === 'paid') {
            return back()
                ->with('message', 'Cette facture est déjà réglée.')
                ->with('alert-type', 'warning');
        }

        // OPCO-EP : pas de règlement à enregistrer dans le CMS (seulement facture + certificat).
        if (app(OpcoInvoiceService::class)->isOpcoInvoice($invoiceId)) {
            return redirect()
                ->route('invoices.list')
                ->with('message', 'Facture OPCO-EP : aucun règlement à enregistrer dans le CMS.')
                ->with('alert-type', 'warning');
        }

        $data = $request->validate([
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'paid_at.before_or_equal' => 'La date de règlement ne peut pas être dans le futur.',
        ]);

        DB::table('invoices')
            ->where('id', $invoiceId)
            ->update([
                'payment_method' => 'virement',
                'status'         => 'paid',
                'paid_at'        => Carbon::parse($data['paid_at'])->toDateTimeString(),
                'failed_reason'  => null,
                'updated_at'     => now(),
            ]);

        // Update invoice & send email with certification
        // Not FIFPL Invoice
        if (!$invoice->id_odoo) {
            // Disable cart to sync and create invoice paper
            $cartController = new CartController();
            $cartController->complete($invoice->id);

            // Sync and create document
            $invoiceId = $invoice->id;
            dispatch(function () use ($invoiceId) {
                $invController = new InvoiceController();
                $invController->generateInvoiceDocument($invoiceId);
            })->afterResponse();
        } else { // FIFPL
            // Clear notification
            SystemNotification::where('user_id', $invoice->user_id)
                ->where('entity_type', 'FifplInvoice')
                ->where('entity_id', $invoice->id)->delete();

            $invoiceId = $invoice->id;
            dispatch(function () use ($invoiceId) {
                // Create FIFPL Invoice & Certification
                $invController = new InvoiceController();
                $invController->generateFifplInvoiceDocument($invoiceId);
            })->afterResponse();
        }

        return redirect()
            ->route('invoices.list')
            ->with('message', 'Facture ' . $invoice->no . ' marquée comme réglée par virement.')
            ->with('alert-type', 'success');
    }

    /**
     * Crée la facture (Odoo + PDF) d'UNE convention OPCO-EP choisie, sans attestation.
     * POST /dashboard/invoices/opco/generate  (contract_id)
     */
    public function generateOpcoInvoice(Request $request)
    {
        abort_unless(self::canManageOpco(), 403);

        $data = $request->validate(['contract_id' => ['required', 'integer']]);
        $opco = app(OpcoInvoiceService::class);

        $contract = $opco->contractsWithoutInvoice()->firstWhere('id', (int) $data['contract_id']);
        if (!$contract) {
            return back()
                ->with('message', 'Cette convention OPCO-EP n\'existe pas ou a déjà sa facture.')
                ->with('alert-type', 'error');
        }

        try {
            $invoice = $opco->issueForContract($contract);
        } catch (Exception $e) {
            Log::error($e->getMessage(), ['contract-' . $contract->id, 'opco-invoice-create']);
            return back()
                ->with('message', "Facture de la convention {$contract->no} non créée : " . $e->getMessage())
                ->with('alert-type', 'error');
        }

        return redirect()
            ->route('invoices.list', ['q' => $invoice->no])
            ->with('message', "Facture {$invoice->no} créée pour la convention {$contract->no}.")
            ->with('alert-type', 'success');
    }

    /**
     * Facture OPCO-EP réglée par l'OPCO : « En attente » → « Payée », définitivement (pas de retour).
     * Tous les utilisateurs du CMS. Rien dans Stripe ni Odoo (le règlement Odoo reste à la comptabilité).
     * POST /dashboard/invoices/{invoice}/opco-paid
     */
    public function markOpcoPaid(int $invoiceId)
    {
        $opco = app(OpcoInvoiceService::class);
        $invoice = Invoice::findOrFail($invoiceId);
        abort_unless($opco->isOpcoInvoice($invoice->id), 404);

        // Un seul sens : seulement depuis « En attente », de façon atomique
        $updated = DB::table('invoices')
            ->where('id', $invoice->id)
            ->where('status', '!=', 'paid')
            ->update([
                'status'         => 'paid',
                'payment_method' => 'opco',
                'paid_at'        => now(),
                'updated_at'     => now(),
            ]);

        if (!$updated) {
            return back()
                ->with('message', "La facture {$invoice->no} est déjà payée.")
                ->with('alert-type', 'info');
        }

        Log::info('OPCO : facture marquée payée', ['inv-' . $invoice->id, 'user-' . optional(auth()->user())->id]);

        return back()
            ->with('message', "Facture {$invoice->no} marquée comme payée par l'OPCO-EP.")
            ->with('alert-type', 'success');
    }

    /**
     * Crée l'attestation d'une facture OPCO-EP (PDF de tous les certificats, sur S3). N'envoie rien.
     * POST /dashboard/invoices/{invoice}/opco-attestation
     */
    public function createOpcoAttestation(int $invoiceId)
    {
        abort_unless(self::canManageOpco(), 403);

        $opco = app(OpcoInvoiceService::class);
        $invoice = Invoice::findOrFail($invoiceId);
        abort_unless($opco->isOpcoInvoice($invoice->id), 404);

        try {
            $opco->createAttestation($invoice);
        } catch (Exception $e) {
            Log::error($e->getMessage(), ['inv-' . $invoiceId, 'opco-attestation']);
            return back()
                ->with('message', 'Attestation non créée : ' . $e->getMessage())
                ->with('alert-type', 'error');
        }

        return back()
            ->with('message', "Attestation de la facture {$invoice->no} créée. Vérifiez-la, puis envoyez les certificats aux apprenants.")
            ->with('alert-type', 'success');
    }

    /**
     * Envoie à chaque apprenant SA page de l'attestation (après vérification) ; learner_id : un seul apprenant.
     * POST /dashboard/invoices/{invoice}/opco-certificates
     */
    public function sendOpcoCertificates(Request $request, int $invoiceId)
    {
        abort_unless(self::canManageOpco(), 403);

        $opco = app(OpcoInvoiceService::class);
        $invoice = Invoice::findOrFail($invoiceId);
        abort_unless($opco->isOpcoInvoice($invoice->id), 404);

        // learner_id vide = tous les apprenants
        $learnerId = $request->integer('learner_id') ?: null;

        try {
            $result = $opco->sendCertificates($invoice, $learnerId);
        } catch (Exception $e) {
            Log::error($e->getMessage(), ['inv-' . $invoiceId, 'opco-certificates']);
            return back()
                ->with('message', 'Certificats non envoyés : ' . $e->getMessage())
                ->with('alert-type', 'error');
        }

        $message = "Certificat envoyé à {$result['sent']} apprenant(s).";
        if ($result['no_email']) {
            $message .= ' Sans e-mail : ' . implode(', ', $result['no_email']) . '.';
        }
        if ($result['failed']) {
            $message .= ' Échec : ' . implode(', ', $result['failed']) . ' (voir les logs).';
        }

        return back()
            ->with('message', $message)
            ->with('alert-type', ($result['no_email'] || $result['failed']) ? 'warning' : 'success');
    }

    // Generate normal invoice
    public function generateInvoiceDocument($id)
    {
        $invoice = Invoice::find($id);
        $cart = Cart::where('invoice_id', $id)->first();

        if (!$cart) {
            return;
        }

        $user = User::find($invoice->user_id);
        // Sync
        $invoice->client_name = $user->name . ' ' . $user->first_name;
        $invoice->client_email = $user->email;

        $invoice->amount_ht = $invoice->amount + $invoice->discount_amount - $invoice->tva_amount;
        $invoice->tva = $cart->tva;
        $invoice->unit_price = $cart->unit_price;

        // Numéro F-AA-NNNN : suite du plus grand numéro F- de l'année, sans trou.
        // (Avant : nombre de lignes « invoices » de l'année + 1, qui comptait aussi les lignes
        // d'autres sources — rendez-vous experts, factures Odoo VE… — et faisait sauter des numéros.)
        // Verrou MySQL : deux paiements simultanés ne prennent pas le même numéro.
        DB::select("SELECT GET_LOCK('invoice-number-F', 10)");
        try {
            $prefix = 'F-' . date('y') . '-';
            $last = (int) Invoice::where('no', 'like', $prefix . '%')
                ->max(DB::raw('CAST(SUBSTRING(no, ' . (strlen($prefix) + 1) . ') AS UNSIGNED)'));

            do {
                $last++;
                $invoiceNo = $prefix . str_pad((string) $last, 4, '0', STR_PAD_LEFT);
            } while (Invoice::where('no', $invoiceNo)->exists());

            $invoice->no = $invoiceNo;
            $invoice->save();
        } finally {
            DB::select("SELECT RELEASE_LOCK('invoice-number-F')");
        }

        $generatePdfController = new GeneratePdfController();
        $generatePdfController->paymentInvoice($invoice);
        return;
    }

    // Generate fifpl invoice - no pay
    public function generateFiflplInvoiceNotPayByCart($cartId)
    {
        $controller = new OdooInvoiceController(app(OdooService::class));
        $generatePdfController = new GeneratePdfController();

        $cart = Cart::find($cartId);
        if (!$cart) {
            return false;
        }

        $cart->load(['members', 'items', 'items.package']);

        // Create label of item
        $label = implode(', ', $cart->items->map(fn($item) => $item->package->name)->toArray());

        try {
            foreach ($cart->members as $member) {
                $memberContract = Contract::where('cart_id', $cart->id)
                    ->where('beneficiary_id', $member->id)
                    ->where('type', 'signed')
                    ->with('company')
                    ->first();

                // Get Invoice No                    
                $invoice = $controller->createInvoice([
                    'partner_id' => $member->id_odoo_partner,
                    'invoice_date' => Carbon::parse($memberContract->created_at)->addMonth()->format('Y-m-d'),
                    'invoice_date_due' => Carbon::parse($memberContract->created_at)->addMonth(2)->format('Y-m-d'),
                    'ref' => $memberContract->no,
                    'lines' => [[
                        'name' => $label,
                        'price_unit' => $cart->price_total_ht,
                        'quantity' => 1,
                        'tax_ids'    => [[6, 0, [3]]],
                        'ref'
                    ]]
                ]);

                if (!$invoice) {
                    Log::channel('api')->error('FAILED: Can not create invoice with Odoo', ['cart-' . $cart->id, 'user-' . $member->id]);
                    continue;
                }

                $invoiceNo = $invoice['name'];
                $invoiceOdooId = $invoice['id'];

                $invoice = Invoice::create([
                    'no' => $invoiceNo,
                    'user_id' => $member->id,
                    'amount' => $cart->price_total,
                    'currency' => 'eur',
                    'status' => Invoice::STATUS_PENDING,
                    'id_odoo' => $invoiceOdooId,

                    'unit_price' => $cart->price_total_ht,
                    'tva' => $cart->tva,
                    'tva_amount' => $cart->tva_total,
                    'amount_ht' => $cart->price_total_ht,
                    'sent_at' => Carbon::parse($memberContract->created_at)->addMonth(),

                    'client_name' => $member->name . ($member->first_name ? ' ' : '') . $member->first_name,
                    'client_email' => $member->email,
                    'client_company' => $memberContract->company->name,
                    'client_address' => $memberContract->company->address
                ]);
                $memberContract->invoice_id = $invoice->id;
                $memberContract->save();

                $invoicePdfLink = $generatePdfController->fifplPaymentInvoice(
                    $invoice->no,
                    $memberContract->no,
                    $memberContract->created_at->addMonth()->format('d/m/Y'),
                    $memberContract->created_at->addMonth(2)->format('d/m/Y'),
                    $memberContract->created_at->addMonth()->format('d/m/Y') . ' - ' . $memberContract->created_at->addMonth(2)->format('d/m/Y'),
                    $memberContract->company,
                    $member,
                    $cart->items,
                    Organization::FIFPL_UNIT_PRICE->value,
                    (float) setting('system.tva'),
                    $cart->duration_total_days,
                    null,
                );

                //
                $invoice->document = $invoicePdfLink;
                $invoice->save();

                $controller->uploadInvoicePdf(
                    $invoiceNo,
                    $invoicePdfLink,
                    str_replace('/', '_', $invoiceNo) . '.pdf',
                    'Facture - CMS'
                );
            }

            Log::info('Generated FIFPL invoice - no pay', ['cart-' . $cartId]);
            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage(), ['cart-' . $cartId, 'create-no-pay-invoice-fifpl']);
            return false;
        }
    }

    // Generate fifpl invoice - paid
    public function generateFifplInvoiceDocument($invoiceId)
    {
        $controller = new OdooInvoiceController(app(OdooService::class));
        $generatePdfController = new GeneratePdfController();

        $invoice = Invoice::find($invoiceId);
        $memberContract = Contract::where('invoice_id', $invoiceId)
            ->where('beneficiary_id', $invoice->user_id)
            ->where('type', 'signed')
            ->with('company')
            ->first();
        $member = User::find($memberContract->beneficiary_id);
        $cart = Cart::find($memberContract->cart_id);
        $cart->load(['members', 'items', 'items.package']);

        if (!$invoice || !$memberContract) {
            return false;
        }

        try {
            // Invoice
            $invoicePdfLink = $generatePdfController->fifplPaymentInvoice(
                $invoice->no,
                $memberContract->no,
                $memberContract->created_at->addMonth()->format('d/m/Y'),
                $memberContract->created_at->addMonth(2)->format('d/m/Y'),
                $memberContract->created_at->addMonth()->format('d/m/Y') . ' - ' . $memberContract->created_at->addMonth(2)->format('d/m/Y'),
                $memberContract->company,
                $member,
                $cart->items,
                Organization::FIFPL_UNIT_PRICE->value,
                (float) setting('system.tva'),
                $cart->duration_total_days,
                now()->format('d/m/Y'),
            );
            $invoice->document = $invoicePdfLink;
            $invoice->save();

            // Certif link
            $cetifPdfLink = $generatePdfController->fifplCertificate(
                $member->id,
                $member->name . ' ' . $member->first_name,
                $memberContract->created_at->addMonth(),
                $memberContract->created_at->addMonth(2),
                $cart->items,
                $cart->duration_total,
                sizeof($cart->items),
                $invoice->amount_ht,
                $invoice->amount
            );
            $invoice->certification = $cetifPdfLink;
            $invoice->sent_at = $memberContract->created_at->addMonth(2);
            $invoice->save();

            // Sync Odoo
            $controller->uploadInvoicePdf(
                $invoice->no,
                $invoicePdfLink,
                str_replace('/', '_', $invoice->no) . '.pdf',
                'Facture acquittée - CMS'
            );
            $controller->markInvoiceAsPaid($invoice->id_odoo, $invoice->amount, $invoice->paid_at->format('Y-m-d'));


            // Send email when out of end date
            if (now() >= $memberContract->created_at->addMonth(2)) {
                Mail::to($invoice->user->email)->send(new SendFifplCompletedDocumentMail($invoice->user_id, $invoice));
            }

            Log::info('Generated FIFPL invoice & certif', ['inv-' . $invoiceId]);
            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage(), ['inv-' . $invoiceId, 'create-fifpl-invoice-and-certif']);
            return false;
        }
    }
}
