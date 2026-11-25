<?php

namespace App\Http\Controllers;

use App\Services\Invoices\InvoiceTestSendService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CMS — Factures → « Envoi test » : refait facture + certificat + programme d'une facture existante
 * et envoie l'e-mail de fin de formation à l'adresse saisie. Rien n'est enregistré.
 */
class InvoiceTestController extends Controller
{
    public function __construct(private InvoiceTestSendService $tester) {}

    /** GET /admin/dashboard/invoices/test-send */
    public function form(Request $request)
    {
        return view('invoices.test-send', [
            'candidates' => $this->tester->candidates(),
            'selected'   => (string) old('invoice_id', $request->query('invoice', '')),
            'email'      => old('email', $request->user()?->email),
            'result'     => session('test_send_result'),
        ]);
    }

    /** POST /admin/dashboard/invoices/test-send */
    public function send(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'email'      => ['required', 'email:rfc'],
        ], [
            'invoice_id.exists' => 'Cette facture n\'existe pas.',
        ]);

        try {
            $result = $this->tester->send((int) $data['invoice_id'], $data['email']);
        } catch (Throwable $e) {
            Log::error($e->getMessage(), ['inv-' . $data['invoice_id'], 'invoice-test-send']);

            return back()
                ->withInput()
                ->with('message', 'Envoi test impossible : ' . $e->getMessage())
                ->with('alert-type', 'error');
        }

        return redirect()
            ->route('invoices.test-send', ['invoice' => $data['invoice_id']])
            ->withInput()
            ->with('test_send_result', $result + ['email' => $data['email']])
            ->with('message', "E-mail test de la facture {$result['invoice_no']} envoyé à {$data['email']}.")
            ->with('alert-type', 'success');
    }
}
