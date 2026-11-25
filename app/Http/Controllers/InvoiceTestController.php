<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Invoices\InvoiceTestSendService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Test admin /admin/dashboard/test-invoices (hors menu) : refait facture + certificat + programme d'une facture existante
 * et envoie l'e-mail de fin de formation à l'adresse saisie. Rien n'est enregistré.
 */
class InvoiceTestController extends Controller
{
    public function __construct(private InvoiceTestSendService $tester) {}

    /** GET /admin/dashboard/test-invoices */
    public function form(Request $request)
    {
        return view('invoices.test-invoices', [
            'candidates' => $this->tester->candidates(),
            'packages'   => $this->tester->packages(),
            'selected'   => (string) old('invoice_id', $request->query('invoice', '')),
            'email'      => old('email', $request->user()?->email),
            'result'     => session('test_send_result'),
        ]);
    }

    /** POST /admin/dashboard/test-invoices */
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
            ->route('test-invoices', ['invoice' => $data['invoice_id']])
            ->withInput()
            ->with('test_send_result', $result + ['email' => $data['email']])
            ->with('message', "E-mail test de la facture {$result['invoice_no']} envoyé à {$data['email']}.")
            ->with('alert-type', 'success');
    }

    /** POST /admin/dashboard/test-invoices/fake-opco — devis OPCO-EP fictif, rien enregistré */
    public function fakeOpco(Request $request)
    {
        $data = $request->validate([
            'fake_email'    => ['required', 'email:rfc'],
            'learners'      => ['required', 'integer', 'min:1', 'max:20'],
            'package_ids'   => ['nullable', 'array'],
            'package_ids.*' => ['integer', 'exists:packages,id'],
        ]);

        try {
            $result = $this->tester->sendFakeOpco(
                $data['fake_email'],
                User::findOrFail($request->user()->id),
                array_map('intval', $data['package_ids'] ?? []),
                (int) $data['learners']
            );
        } catch (Throwable $e) {
            Log::error($e->getMessage(), ['invoice-test-fake-opco']);

            return back()
                ->withInput()
                ->with('message', 'Devis OPCO fictif impossible : ' . $e->getMessage())
                ->with('alert-type', 'error');
        }

        return redirect()
            ->route('test-invoices')
            ->withInput()
            ->with('test_send_result', $result + ['email' => $data['fake_email']])
            ->with('message', "Devis OPCO-EP fictif envoyé à {$data['fake_email']}.")
            ->with('alert-type', 'success');
    }
}
