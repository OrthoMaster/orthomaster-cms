<?php

namespace App\Http\Controllers\Odoo;

use App\Http\Controllers\Controller;
use App\Services\OdooService;
use Illuminate\Support\Facades\Log;

class OdooInvoiceController extends Controller
{
    public function __construct(
        protected OdooService $odooService
    ) {}

    /**
     * Create and post invoice on Odoo, return invoice number
     *
     * @param array $invoiceData  ['partner_id', 'invoice_date', 'lines' => [['name', 'price_unit', 'quantity']]]
     * @return string|false  Invoice number (e.g. INV/2026/00042) or false on failure
     */
    public function createInvoice(array $invoiceData): bool|array
    {
        try {
            // Build invoice lines
            $invoiceLines = [];
            foreach ($invoiceData['lines'] ?? [] as $line) {
                $invoiceLines[] = [0, 0, [
                    'name'       => $line['name']       ?? 'Service',
                    'price_unit' => $line['price_unit']  ?? 0,
                    'quantity'   => $line['quantity']    ?? 1,
                    'tax_ids'    => $line['tax_ids']     ?? [],
                ]];
            }

            // Create invoice in draft state
            $odooInvoiceId = $this->odooService->create('account.move', [
                'move_type'          => 'out_invoice',
                'partner_id'         => $invoiceData['partner_id'],
                'invoice_date'       => $invoiceData['invoice_date'],
                'invoice_date_due'   => $invoiceData['invoice_date_due'] ?? null,
                'ref'                => $invoiceData['ref'] ?? null,  // référence interne
                'narration'          => $invoiceData['narration'] ?? null,
                'invoice_line_ids'   => $invoiceLines,
            ]);

            if (!is_numeric($odooInvoiceId)) {
                Log::channel('api')->error('Odoo invoice create failed for partner: ' . $invoiceData['partner_id'], ['odoo', 'invoice']);
                return false;
            }

            // Change status
            $this->odooService->write('account.move', [$odooInvoiceId], ['state' => 'posted']);

            // Read back the generated invoice number
            $invoice = $this->odooService->searchRead(
                'account.move',
                [['id', '=', $odooInvoiceId]],
                ['id', 'name', 'state'],
                1
            );

            if (empty($invoice) || ($invoice[0]['state'] ?? '') !== 'posted') {
                Log::channel('api')->error('Odoo invoice not posted, id: ' . $odooInvoiceId, ['odoo', 'invoice']);
                return false;
            }

            return $invoice[0];
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['odoo', 'invoice', 'create']);
            return false;
        }
    }

    /**
     * Upload a PDF file to an existing Odoo invoice
     *
     * @param string $invoiceNumber  e.g. "INV/2026/00042"
     * @param string $pdfPath        Absolute path to PDF file (storage_path(...))
     * @param string $fileName       File name to display in Odoo
     * @return bool
     */
    public function uploadInvoicePdf(string $invoiceNumber, string $pdfPath, string $fileName, string $message)
    {
        try {
            // Find invoice by number
            $invoice = $this->odooService->searchRead(
                'account.move',
                [['name', '=', $invoiceNumber], ['move_type', '=', 'out_invoice']],
                ['id', 'name'],
                1
            );

            if (empty($invoice)) {
                Log::channel('api')->error('Odoo invoice not found: ' . $invoiceNumber, ['odoo', 'invoice', 'upload-pdf']);
                return false;
            }

            $invoiceId = $invoice[0]['id'];

            // Encode PDF to base64
            $isUrl = filter_var($pdfPath, FILTER_VALIDATE_URL);

            if ($isUrl) {
                $fileContent = @file_get_contents($pdfPath);
            } else {
                if (!file_exists($pdfPath)) {
                    Log::channel('api')->error('PDF file not found: ' . $pdfPath, ['odoo', 'invoice', 'upload-pdf']);
                    return false;
                }
                $fileContent = file_get_contents($pdfPath);
            }

            if ($fileContent === false) {
                Log::channel('api')->error('Cannot read PDF: ' . $pdfPath, ['odoo', 'invoice', 'upload-pdf']);
                return false;
            }

            $base64Content = base64_encode($fileContent);

            // Create attachment linked to the invoice
            $attachmentId = $this->odooService->create('ir.attachment', [
                'name'      => $fileName,       // e.g. "invoice-2026-00042.pdf"
                'type'      => 'binary',
                'datas'     => $base64Content,
                'mimetype'  => 'application/pdf',
                'res_model' => 'account.move',
                'res_id'    => $invoiceId,
            ]);

            if (!is_numeric($attachmentId)) {
                Log::channel('api')->error('Odoo attachment create failed for invoice: ' . $invoiceNumber, ['odoo', 'invoice', 'upload-pdf']);
                return false;
            }
            $this->odooService->write('account.move', [$invoiceId], [
                'message_main_attachment_id' => $attachmentId
            ]);

            // Message
            $this->odooService->write('account.move', [$invoiceId], [
                'message_main_attachment_id' => $attachmentId
            ]);

            // Post to chatter as internal note
            $this->odooService->callMethod(
                'account.move',
                'message_post',
                [[$invoiceId]],
                [
                    'body'           => $message,
                    'attachment_ids' => [$attachmentId],
                    'message_type'   => 'comment',
                    'subtype_xmlid'  => 'mail.mt_note',
                ]
            );

            return true;
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['odoo', 'invoice', 'upload-pdf']);
            return false;
        }
    }

    /**
     * Make invoice as Paid
     * 
     * @param string $odooInvoiceId  e.g. "101"
     * @param string $amount
     * @param string $paymentDate (Y-m-d) : date of payment; null is unpay
     * @return bool
     */
    public function markInvoiceAsPaid(int $odooInvoiceId, float $amount, ?string $paymentDate = null): bool
    {
        $journalId = 15; // Stripe - Application journal

        try {
            $date = $paymentDate ?? now()->format('Y-m-d');

            // Create payment register wizard
            $wizardId = $this->odooService->create(
                'account.payment.register',
                [
                    'amount'       => $amount,
                    'payment_date' => $date,
                    'journal_id'   => $journalId,
                ],
                [
                    'active_model' => 'account.move',
                    'active_ids'   => [$odooInvoiceId],
                ]
            );

            if (!is_numeric($wizardId)) {
                Log::channel('api')->error('Wizard create failed for invoice: ' . $odooInvoiceId, ['odoo', 'invoice', 'payment']);
                return false;
            }

            // Trigger create payment and reconcile
            try {
                $this->odooService->callMethod(
                    'account.payment.register',
                    'action_create_payments',
                    [[$wizardId]],
                    ['context' => ['active_model' => 'account.move', 'active_ids' => [$odooInvoiceId]]]
                );
            } catch (\Exception $e) {
                if (!str_contains($e->getMessage(), 'cannot marshal None')) {
                    Log::channel('api')->error('action_create_payments failed: ' . $e->getMessage(), ['odoo', 'invoice', 'payment']);
                    return false;
                }
            }

            // Verify
            $check = $this->odooService->searchRead(
                'account.move',
                [['id', '=', $odooInvoiceId]],
                ['payment_state'],
                1
            );

            $state = $check[0]['payment_state'] ?? 'unknown';

            if (!in_array($state, ['paid', 'in_payment'])) {
                Log::channel('api')->error('Invoice not paid, state: ' . $state, ['odoo', 'invoice', 'payment']);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['odoo', 'invoice', 'payment']);
            return false;
        }
    }
}
