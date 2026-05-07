<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Odoo\OdooInvoiceController;
use App\Jobs\SendExpoNotificationJob;
use App\Mail\RemindManagerMail;
use App\Models\Cart;
use App\Models\Contract;
use App\Models\FundingOrganization;
use App\Models\Invoice;
use App\Models\User;
use App\Organization;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TestingController extends Controller
{
    public function sendEmail()
    {
        $email = 'fx.tiennguyen92@gmail.com';
        $user = User::first();
        Mail::to($email)->send(
            new RemindManagerMail(
                $user,
                $email
            )
        );
    }

    public function backupCreateContract(Request $request)
    {
        if (!$request->filled('contract_id') || !$request->filled('signature_link') || !$request->filled('signatory')) {
            return 'False';
        }

        $generatePdfController = new GeneratePdfController();


        $contract = Contract::find($request->contract_id);
        if (!$contract) {
            return 'False x';
        }

        $cart = Cart::find($contract->cart_id);
        $cart->load(['members', 'items', 'items.package']);
        $organization = FundingOrganization::find($cart->payment_type);

        $user = User::find($contract->created_by);
        $company = $user->companiesUserIsManager()->first();

        $pdfLinks = [];
        if ($cart->payment_type == '1') {
            $itemGroups = $cart->groupItemsByDurationBestFit(Organization::OPCO_MAX_DURATION->value);

            foreach ($itemGroups as $key => $group) {
                $pdf = $generatePdfController->quotationTemp(
                    $cart->id,
                    $contract->no,
                    now()->format('d/m/Y'),
                    now()->addMonth()->format('d/m/Y'),
                    $company,
                    now()->format('d/m/Y') . ' - ' . now()->addMonth()->format('d/m/Y'),
                    $organization->id,
                    $cart->members,
                    $group,
                    Organization::OPCO_UNIT_PRICE->value,
                    (float) setting('system.tva'),
                    $cart->calculateGroupDuration($group),
                    $request->signature_link,
                    $request->signatory
                );
            }

            if ($pdf) {
                array_push($pdfLinks, $pdf);
            }
        }

        return $pdfLinks;
    }

    public function testSendNotification(Request $request)
    {
        SendExpoNotificationJob::dispatch(
            userIds: [4],
            title: 'Noti Test Title',
            body: 'Content Noti Test',
            data: ['action' => 'daily-question'],
            options: ['badge' => 1]
        );
    }

    public function testCreateInvoiceFromContract()
    {
        $controller = new OdooInvoiceController(app(OdooService::class));
        $generatePdfController = new generatePdfController();

        $cartId = 58;
        $cart = Cart::find($cartId);
        $cart->load(['members', 'items', 'items.package']);

        // Create label of item
        $label = implode(', ', $cart->items->map(fn($item) => $item->package->name)->toArray());

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
                'invoice_date_due' => Carbon::parse($memberContract->created_at)->addMonth()->format('Y-m-d'),
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
                $memberContract->created_at->format('d/m/Y'),
                $memberContract->created_at->format('d/m/Y'),
                $memberContract->created_at->format('d/m/Y') . ' - ' . $memberContract->created_at->format('d/m/Y'),
                $memberContract->company,
                $member,
                $cart->items,
                Organization::OPCO_UNIT_PRICE->value,
                (float) setting('system.tva'),
                $cart->duration_total_days,
                null,
            );

            //
            $invoice->document = $invoicePdfLink;
            $invoice->save();

            $uploaded = $controller->uploadInvoicePdf(
                $invoiceNo,
                $invoicePdfLink,
                str_replace('/', '_', $invoiceNo) . '.pdf',
                'Facture - CMS'
            );
        }
    }

    public function testChangeStatusToPaid()
    {
        $controller = new OdooInvoiceController(app(OdooService::class));
        $generatePdfController = new generatePdfController();

        $invoiceId = 89;
        $invoice = Invoice::find($invoiceId);

        $memberContract = Contract::where('invoice_id', $invoiceId)
            // ->where('beneficiary_id', $request->user()->id)
            ->where('type', 'signed')
            ->with('company')
            ->first();
        $member = User::find($memberContract->beneficiary_id);
        $cart = Cart::find($memberContract->cart_id);
        $cart->load(['members', 'items', 'items.package']);
        if (!$invoice || !$memberContract) {
            return false;
        }

        // Invoice
        $invoicePdfLink = $generatePdfController->fifplPaymentInvoice(
            $invoice->no,
            $memberContract->no,
            $memberContract->created_at->format('d/m/Y'),
            $memberContract->created_at->format('d/m/Y'),
            $memberContract->created_at->format('d/m/Y') . ' - ' . $memberContract->created_at->format('d/m/Y'),
            $memberContract->company,
            $member,
            $cart->items,
            Organization::OPCO_UNIT_PRICE->value,
            (float) setting('system.tva'),
            $cart->duration_total_days,
            now()->format('d/m/Y'),
        );
        $invoice->document = $invoicePdfLink;
        $invoice->save();

        $uploaded = $controller->uploadInvoicePdf(
            $invoice->no,
            $invoicePdfLink,
            str_replace('/', '_', $invoice->no) . '.pdf',
            'Facture acquittée - CMS'
        );

        // TODO: Change payment date
        $controller->markInvoiceAsPaid($invoice->id_odoo, 315.00, '2026-03-25');
    }

    public function testFifplCert()
    {
        $generatePdfController = new generatePdfController();
        return $generatePdfController->fifplCertificate2();
    }

    public function testGenerateInvoice()
    {
        $invoiceId = 107;
        $invoiceController = new InvoiceController();
        $invoiceController->generateFifplInvoiceDocument($invoiceId);
    }
}
