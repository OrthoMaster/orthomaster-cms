<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Factures (VE) et avoirs (RVE) des rendez-vous experts.
 * GET /admin/booking/invoices?kind&from&to&q&state[&export=csv]
 *
 * Factures : lignes « invoices » d'une demande (bookings.invoice_id) une fois débitées ;
 * avoirs : kind = credit_note. « À émettre » : débité mais pas encore de numéro Odoo.
 */
class BookingInvoiceController extends Controller
{
    private const TZ = 'Europe/Paris';

    public function index(Request $request)
    {
        $f = [
            'kind'  => in_array($request->query('kind'), ['invoice', 'credit_note'], true) ? $request->query('kind') : null,
            'state' => in_array($request->query('state'), ['issued', 'pending'], true) ? $request->query('state') : null,
            'from'  => $request->date('from')?->toDateString(),
            'to'    => $request->date('to')?->toDateString(),
            'q'     => trim((string) $request->query('q')),
        ];

        $query = $this->query($f);

        if ($request->query('export') === 'csv') {
            return $this->csv($query->get());
        }

        return view('booking.invoices.index', [
            'filters'  => $f,
            'invoices' => $query->paginate(30)->withQueryString(),
            'bookings' => $this->bookingsByInvoice(),
        ]);
    }

    private function query(array $f)
    {
        $bookingInvoiceIds = Booking::whereNotNull('invoice_id')->select('invoice_id');

        return Invoice::query()
            ->where(fn ($q) => $q->whereIn('id', $bookingInvoiceIds)->orWhere('kind', 'credit_note'))
            ->where('status', Invoice::STATUS_PAID)
            ->when($f['kind'] === 'invoice', fn ($q) => $q->where('kind', 'invoice'))
            ->when($f['kind'] === 'credit_note', fn ($q) => $q->where('kind', 'credit_note'))
            ->when($f['state'] === 'issued', fn ($q) => $q->whereNotNull('no'))
            ->when($f['state'] === 'pending', fn ($q) => $q->whereNull('no'))
            ->when($f['from'], fn ($q) => $q->where('paid_at', '>=', Carbon::parse($f['from'], self::TZ)->startOfDay()->utc()))
            ->when($f['to'], fn ($q) => $q->where('paid_at', '<=', Carbon::parse($f['to'], self::TZ)->endOfDay()->utc()))
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('no', 'like', '%' . $f['q'] . '%')
                ->orWhere('client_name', 'like', '%' . $f['q'] . '%')
                ->orWhere('client_email', 'like', '%' . $f['q'] . '%')))
            ->orderByDesc('paid_at')
            ->orderByDesc('id');
    }

    /** invoice_id → booking (rendez-vous d'origine, expert). */
    private function bookingsByInvoice()
    {
        return Booking::with('expert:id,name,honorific')
            ->whereNotNull('invoice_id')
            ->get(['id', 'invoice_id', 'expert_id', 'offer_title', 'kind'])
            ->keyBy('invoice_id');
    }

    private function csv($rows): StreamedResponse
    {
        $bookings = $this->bookingsByInvoice();
        $name = 'factures-rendez-vous-' . now(self::TZ)->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows, $bookings) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel : UTF-8
            fputcsv($out, ['Type', 'Numéro', 'Date', 'Client', 'E-mail', 'Expert', 'Rendez-vous', 'HT', 'TVA', 'TTC', 'Facture d\'origine', 'Remboursement', 'PDF'], ';');
            foreach ($rows as $inv) {
                $avoir = $inv->kind === 'credit_note';
                $b = $bookings->get($avoir ? $inv->parent_invoice_id : $inv->id);
                $sign = $avoir ? -1 : 1;
                fputcsv($out, [
                    $avoir ? 'Avoir' : 'Facture',
                    $inv->no ?: 'à émettre',
                    $inv->paid_at ? Carbon::parse($inv->paid_at)->setTimezone(self::TZ)->format('d/m/Y') : '',
                    $inv->client_name,
                    $inv->client_email,
                    $b?->expert?->name,
                    $b ? '#' . $b->id : '',
                    number_format($sign * (float) $inv->amount_ht, 2, ',', ''),
                    number_format($sign * (float) $inv->tva_amount, 2, ',', ''),
                    number_format($sign * (float) $inv->amount, 2, ',', ''),
                    $avoir ? optional(Invoice::find($inv->parent_invoice_id))->no : '',
                    $avoir ? ($inv->refund_method === 'manual' ? 'Virement ' . $inv->refund_reference : 'Stripe') : '',
                    $inv->document,
                ], ';');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
