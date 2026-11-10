<?php

namespace App\Services\Booking;

use App\Http\Controllers\GeneratePdfController;
use App\Http\Controllers\Odoo\OdooInvoiceController;
use App\Jobs\SendBookingMailJob;
use App\Mail\BookingNotificationMail;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\ExpertOfferMode;
use App\Models\Invoice;
use App\Services\OdooService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Factures et avoirs des rendez-vous experts. Numéros donnés par Odoo (journal de ventes
 * commun avec les formations) : facture VE/AAAA/NNNNN, avoir RVE/AAAA/NNNNN.
 *
 *  - Facture : quand l'expert accepte (montant débité). La ligne « invoices » créée à la
 *    réservation devient la facture : Odoo (out_invoice, TVA 20 %, publiée) → numéro →
 *    PDF « Acquittée » (S3) → PDF joint dans Odoo + paiement enregistré (journal Stripe 15)
 *    → e-mail au demandeur avec le PDF.
 *  - Avoir : à chaque remboursement (journal « refunded » du CMS, ou « refund_synced » du
 *    webhook Stripe). Nouvelle ligne « invoices » (kind credit_note) liée à la facture :
 *    Odoo (out_refund lié à la facture) → numéro → PDF « Remboursé » → Odoo → e-mail.
 *
 * Chaque étape est rejouable (jobs avec nouvelles tentatives, bouton « Relancer » du CMS) :
 * une étape faite n'est pas refaite (numéro, PDF, envoi). Jamais de PDF sans numéro Odoo.
 * Hors transaction : Odoo est un service distant ; la ligne est verrouillée le temps de
 * réserver l'étape Odoo pour qu'un double déclenchement ne crée pas deux pièces.
 */
class BookingInvoiceService
{
    /** Taxe Odoo : TVA 20 % (même id que les factures FIFPL). */
    public const ODOO_TAX_ID = 3;
    /** Journal Odoo des paiements : Stripe - Application. */
    public const ODOO_PAYMENT_JOURNAL = 15;

    private const TZ = 'Europe/Paris';

    public function __construct(private OdooService $odoo)
    {
    }

    /* ===================== FACTURE ===================== */

    /**
     * Émet (ou termine) la facture d'un rendez-vous débité.
     * Retourne false si rien à facturer (offert, pas encore débité).
     */
    public function issueInvoice(int $bookingId): bool
    {
        $booking = Booking::with(['user', 'expert', 'offer', 'blocks'])->find($bookingId);
        if (!$booking || !$booking->payment_required || !$booking->invoice_id) {
            return false;
        }
        $invoice = Invoice::find($booking->invoice_id);
        if (!$invoice || $invoice->status !== Invoice::STATUS_PAID) {
            return false; // pas (encore) débité : pas de facture
        }

        if (!$invoice->no) {
            $this->odooCreate($invoice, function (Invoice $inv) use ($booking) {
                return [
                    'partner_id'       => $this->partnerId($booking),
                    'invoice_date'     => $this->day($inv->paid_at ?? now()),
                    'invoice_date_due' => $this->day($inv->paid_at ?? now()),
                    'ref'              => 'RDV-' . $booking->id,
                    'lines'            => [[
                        'name'       => $this->lineTitle($booking) . ' — ' . $this->lineSub($booking),
                        'price_unit' => (float) $inv->amount_ht,
                        'quantity'   => 1,
                        'tax_ids'    => [[6, 0, [self::ODOO_TAX_ID]]],
                    ]],
                ];
            }, ['kind' => 'invoice', 'booking_id' => $booking->id]);
            $invoice->refresh();
        }

        $this->finish($invoice, $booking, 'out_invoice');

        return true;
    }

    /* ===================== AVOIR ===================== */

    /** Émet (ou termine) l'avoir d'un remboursement (entrée « refunded » ou « refund_synced »). */
    public function issueCreditNote(int $eventId): bool
    {
        $event = BookingEvent::find($eventId);
        if (!$event || !in_array($event->type, ['refunded', 'refund_synced'], true)) {
            return false;
        }
        $amount = round((float) ($event->data['amount'] ?? 0), 2);
        $booking = Booking::with(['user', 'expert', 'offer', 'blocks'])->find($event->booking_id);
        if (!$booking || $amount <= 0 || !$booking->invoice_id) {
            return false;
        }

        // La facture d'abord : un avoir se rattache toujours à une facture numérotée.
        $this->issueInvoice($booking->id);
        $parent = Invoice::find($booking->invoice_id);
        if (!$parent?->no || !$parent->id_odoo) {
            throw new RuntimeException("Facture du rendez-vous #{$booking->id} pas encore émise : avoir reporté.");
        }

        $rate = (float) ($parent->tva ?: $booking->tva_rate ?: 20);
        $ht = round($amount / (1 + $rate / 100), 2);
        $method = ($event->data['method'] ?? null) === 'manual' ? 'manual' : 'stripe';

        $credit = Invoice::firstOrCreate(['booking_event_id' => $event->id], [
            'kind'              => 'credit_note',
            'booking_id'        => $booking->id,
            'parent_invoice_id' => $parent->id,
            'user_id'           => $parent->user_id,
            'status'            => Invoice::STATUS_PAID,
            'paid_at'           => $event->created_at,
            'amount'            => $amount,
            'unit_price'        => $ht,
            'amount_ht'         => $ht,
            'tva'               => $rate,
            'tva_amount'        => round($amount - $ht, 2),
            'currency'          => $parent->currency ?: 'eur',
            'client_name'       => $parent->client_name,
            'client_email'      => $parent->client_email,
            'client_company'    => $parent->client_company,
            'client_address'    => $parent->client_address,
            'refund_method'     => $method,
            'refund_reference'  => $event->data['refund_id'] ?? $event->data['reference'] ?? $event->data['charge'] ?? null,
            'refund_reason'     => mb_substr($this->motif($booking, $event), 0, 500),
        ]);

        if (!$credit->no) {
            $this->odooCreate($credit, function (Invoice $inv) use ($booking, $parent) {
                return [
                    'move_type'         => 'out_refund',
                    'reversed_entry_id' => (int) $parent->id_odoo,
                    'partner_id'        => $this->partnerId($booking),
                    'invoice_date'      => $this->day($inv->paid_at ?? now()),
                    'ref'               => 'RDV-' . $booking->id . ' · avoir #' . $inv->id . ' sur ' . $parent->no,
                    'lines'             => [[
                        'name'       => 'Remboursement — ' . $this->lineTitle($booking) . ' (facture ' . $parent->no . ')',
                        'price_unit' => (float) $inv->amount_ht,
                        'quantity'   => 1,
                        'tax_ids'    => [[6, 0, [self::ODOO_TAX_ID]]],
                    ]],
                ];
            });
            $credit->refresh();
        }

        $this->finish($credit, $booking, 'out_refund', $parent);

        return true;
    }

    /* ===================== ÉTAPES ===================== */

    /**
     * Crée la pièce dans Odoo et garde son numéro. Verrou sur la ligne : un seul appel
     * Odoo à la fois pour une même pièce ; relue sous verrou, une pièce déjà numérotée
     * n'est pas recréée.
     */
    private function odooCreate(Invoice $invoice, callable $payload, array $extra = []): void
    {
        DB::transaction(function () use ($invoice, $payload, $extra) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->first();
            if ($locked->no) {
                return;
            }

            // Déjà créée lors d'un essai interrompu (réponse perdue) : on la reprend, sans doublon.
            $data = $payload($locked);
            $existing = $this->odoo->searchRead('account.move', [
                ['ref', '=', $data['ref']],
                ['move_type', '=', $data['move_type'] ?? 'out_invoice'],
                ['state', '=', 'posted'],
            ], ['id', 'name'], 1);
            $created = $existing[0] ?? $this->odooInvoices()->createInvoice($data);
            if (!$created || empty($created['name'])) {
                $locked->update(['odoo_error' => 'Création Odoo impossible (voir le journal api) — ' . now()->format('d/m/Y H:i')] + $extra);
                throw new RuntimeException("Odoo : pièce non créée pour la ligne invoices #{$locked->id}.");
            }

            $locked->update([
                'no'         => $created['name'],
                'id_odoo'    => $created['id'],
                'issued_at'  => now(),
                'odoo_error' => null,
            ] + $extra);
        });
    }

    /** PDF (une fois), dépôt dans Odoo + paiement, puis e-mail (une fois). */
    private function finish(Invoice $invoice, Booking $booking, string $moveType, ?Invoice $parent = null): void
    {
        if (!$invoice->document) {
            $path = 'factures/rendez-vous/' . str_replace('/', '_', $invoice->no) . '.pdf';
            $pdf = Pdf::loadView('pdf.booking_invoice', $this->pdfData($invoice, $booking, $parent));
            $pdf->setOption('isPhpEnabled', true);
            $pdf->setOption('isRemoteEnabled', true);
            $pdf->setPaper('A4', 'portrait');
            if (!Storage::disk('s3')->put($path, $pdf->output())) {
                throw new RuntimeException("PDF non enregistré : {$invoice->no}.");
            }
            $invoice->update(['document' => Storage::disk('s3')->url($path)]);

            // Odoo : PDF joint et paiement (encaissé / remboursé). Échec : noté, sans bloquer l'envoi.
            $odoo = $this->odooInvoices();
            $ok = $odoo->uploadInvoicePdf(
                $invoice->no,
                $invoice->document,
                str_replace('/', '_', $invoice->no) . '.pdf',
                $moveType === 'out_refund' ? 'Avoir rendez-vous expert - CMS' : 'Facture acquittée rendez-vous expert - CMS',
                $moveType
            );
            $ok = $odoo->markInvoiceAsPaid(
                (int) $invoice->id_odoo,
                (float) $invoice->amount,
                $this->day($invoice->paid_at ?? now()),
                self::ODOO_PAYMENT_JOURNAL
            ) && $ok;
            if (!$ok) {
                $invoice->update(['odoo_error' => 'PDF ou paiement non enregistré dans Odoo — ' . now()->format('d/m/Y H:i')]);
            }
        }

        if (!$invoice->sent_at) {
            $this->mail($invoice, $booking, $parent);
            $invoice->update(['sent_at' => now(), 'sent_cnt' => (int) $invoice->sent_cnt + 1]);
        }
    }

    /** Renvoi de l'e-mail (bouton du CMS). */
    public function resend(Invoice $invoice): void
    {
        $booking = Booking::with(['user', 'expert', 'offer', 'blocks'])->findOrFail($invoice->booking_id);
        $parent = $invoice->parent_invoice_id ? Invoice::find($invoice->parent_invoice_id) : null;
        $this->mail($invoice, $booking, $parent);
        $invoice->update(['sent_at' => now(), 'sent_cnt' => (int) $invoice->sent_cnt + 1]);
    }

    private function mail(Invoice $invoice, Booking $booking, ?Invoice $parent): void
    {
        if (!$invoice->client_email || !$invoice->document) {
            return;
        }
        $avoir = $invoice->kind === 'credit_note';
        $expert = $this->expertName($booking);
        $path = 'factures/rendez-vous/' . str_replace('/', '_', $invoice->no) . '.pdf';

        SendBookingMailJob::dispatch($invoice->client_email, new BookingNotificationMail(
            subjectLine: $avoir
                ? "Votre avoir {$invoice->no} — remboursement de " . $this->money($invoice->amount)
                : "Votre facture {$invoice->no} — rendez-vous avec {$expert}",
            heading: $avoir ? 'Votre avoir' : 'Votre facture',
            name: $invoice->client_name ?: 'Madame, Monsieur',
            lines: $avoir
                ? ['Vous trouverez ci-joint l\'avoir <strong>' . e($invoice->no) . '</strong> correspondant au remboursement de '
                    . e($this->money($invoice->amount)) . ' sur la facture ' . e($parent?->no ?? '') . '.',
                    ...array_map(fn ($l) => nl2br(e($l)), array_filter([$this->comment($invoice)]))]
                : ['Vous trouverez ci-joint la facture acquittée <strong>' . e($invoice->no) . '</strong> de votre rendez-vous avec <strong>'
                    . e($expert) . '</strong>.'],
            details: [
                ($avoir ? 'Avoir' : 'Facture') => $invoice->no,
                'Montant TTC'                  => $this->money($invoice->amount),
            ] + ($avoir && $invoice->refund_reason ? ['Motif' => strtok($invoice->refund_reason, "\n")] : []),
            action: ['text' => 'Télécharger le PDF', 'url' => $invoice->document],
            note: 'Vos factures et avoirs sont aussi dans l\'application : Profil → Devis & Factures.',
            attachmentPath: $path,
            attachmentName: str_replace('/', '_', $invoice->no) . '.pdf',
        ));
    }

    /* ===================== CONTENU ===================== */

    /** Variables du gabarit pdf.booking_invoice. */
    public function pdfData(Invoice $invoice, Booking $booking, ?Invoice $parent = null): array
    {
        $avoir = $invoice->kind === 'credit_note';
        $date = $this->fr($invoice->issued_at ?? now());
        $paid = $this->fr($invoice->paid_at ?? now());
        $manual = $invoice->refund_method === 'manual';
        $full = $parent && abs((float) $invoice->amount - (float) $parent->amount) < 0.01;

        return [
            'kind'        => $avoir ? 'credit_note' : 'invoice',
            'no'          => $invoice->no,
            'company'     => GeneratePdfController::COMPANY,
            'client'      => [
                'name'    => $invoice->client_name,
                'company' => $invoice->client_company,
                'address' => $invoice->client_address,
                'email'   => $invoice->client_email,
            ],
            'date'        => $date,
            'paidDate'    => $paid,
            'parentNo'    => $parent?->no,
            'parentDate'  => $parent ? $this->fr($parent->issued_at ?? $parent->paid_at ?? now()) : null,
            'reference'   => 'Rendez-vous n° ' . $booking->id,
            'expertName'  => $this->expertName($booking),
            'format'      => $booking->mode === ExpertOfferMode::ONSITE ? 'En présentiel' : 'En visio',
            'participant' => $invoice->client_name,
            'motif'       => $invoice->refund_reason,
            'lineTitle'   => $avoir ? 'Remboursement — ' . $this->lineTitle($booking) : $this->lineTitle($booking),
            'lineSub'     => $avoir
                ? ($full
                    ? 'Annule la facture ' . $parent?->no . ' (remboursement intégral)'
                    : 'Remboursement partiel de la facture ' . $parent?->no . ' (' . $this->money($invoice->amount) . ' sur ' . $this->money($parent?->amount) . ' TTC)')
                : $this->lineSub($booking),
            'quantity'    => $this->quantity($booking),
            'ht'          => (float) $invoice->amount_ht,
            'tvaRate'     => (float) $invoice->tva,
            'tva'         => (float) $invoice->tva_amount,
            'ttc'         => (float) $invoice->amount,
            'settled'     => true,
            'settledLine' => $avoir
                ? 'Remboursé le ' . $paid . ($manual ? ' par virement' : ' sur la carte bancaire')
                : 'Payé le ' . $paid . ' par carte bancaire',
            'paymentNote' => $avoir
                ? 'Mode de remboursement : <strong>' . ($manual ? 'virement' : 'carte bancaire') . '</strong>'
                : 'Mode de paiement : <strong>carte bancaire</strong> (débitée à la confirmation du rendez-vous par l\'expert)',
        ];
    }

    private function lineTitle(Booking $booking): string
    {
        if ($booking->isConsultation()) {
            $subject = trim((string) $booking->subject);

            return 'Consultation' . ($subject !== '' ? ' : ' . mb_strimwidth($subject, 0, 90, '…') : '');
        }

        return 'Thème : ' . ($booking->offer_title ?: $booking->offer?->title ?: '—');
    }

    /** « Rendez-vous avec Dr X · 12/11/2026 » (ou « 12/11/2026 – 14/11/2026 »). */
    private function lineSub(Booking $booking): string
    {
        $dates = $booking->blocks->pluck('date')->filter()->map(fn ($d) => Carbon::parse($d))->sort()->values();
        $when = $dates->isEmpty() ? ''
            : ($dates->first()->isSameDay($dates->last())
                ? $dates->first()->format('d/m/Y')
                : $dates->first()->format('d/m/Y') . ' – ' . $dates->last()->format('d/m/Y'));

        return 'Rendez-vous avec ' . $this->expertName($booking) . ($when ? ' · ' . $when : '');
    }

    private function quantity(Booking $booking): string
    {
        if ($booking->isConsultation()) {
            return ((int) $booking->duration_minutes) . ' min';
        }
        $n = max(1, $booking->blocks->count() ?: (int) $booking->blocks_count);

        return $n . ' demi-journée' . ($n > 1 ? 's' : '');
    }

    /** Précision de l'avoir : ce qui suit la première ligne du motif. */
    private function comment(Invoice $invoice): ?string
    {
        $parts = explode("\n", (string) $invoice->refund_reason, 2);

        return isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : null;
    }

    /** Motif de l'avoir, d'après le remboursement (+ précision éventuelle, ligne suivante). */
    private function motif(Booking $booking, BookingEvent $event): string
    {
        $d = $event->data ?? [];
        $date = $this->fr($event->created_at);

        // Remboursement depuis l'écran « Rembourser » : motif choisi par l'administration.
        // Précision éventuelle sur une seconde ligne.
        if (!empty($d['motif'])) {
            $comment = trim((string) ($d['comment'] ?? ''));

            return rtrim((string) $d['motif'], '. ') . ", le {$date}." . ($comment !== '' ? "\n" . $comment : '');
        }

        return match ($d['reason'] ?? null) {
            'cancel' => ($d['party'] ?? null) === 'expert'
                ? "Annulation du rendez-vous par l'expert (" . $this->expertName($booking) . "), le {$date}."
                : "Annulation du rendez-vous par le participant, le {$date}.",
            'noshow' => "Absence signalée lors du rendez-vous, traitée le {$date}.",
            default  => $event->type === 'refund_synced'
                ? "Remboursement effectué le {$date}."
                : "Remboursement accordé le {$date}.",
        };
    }

    private function expertName(Booking $booking): string
    {
        $e = $booking->expert;

        return $e ? trim((ExpertProfileService::honorificShort($e->honorific) ?? '') . ' ' . $e->name) : "l'expert";
    }

    private function partnerId(Booking $booking): int
    {
        $id = (int) ($booking->user?->id_odoo_partner ?? 0);
        if (!$id) {
            throw new RuntimeException("Le demandeur du rendez-vous #{$booking->id} n'a pas de contact Odoo (users.id_odoo_partner).");
        }

        return $id;
    }

    private function odooInvoices(): OdooInvoiceController
    {
        return new OdooInvoiceController($this->odoo);
    }

    private function day($date): string
    {
        return Carbon::parse($date)->setTimezone(self::TZ)->format('Y-m-d');
    }

    private function fr($date): string
    {
        return Carbon::parse($date)->setTimezone(self::TZ)->format('d/m/Y');
    }

    private function money($v): string
    {
        return number_format((float) $v, 2, ',', ' ') . ' €';
    }
}
