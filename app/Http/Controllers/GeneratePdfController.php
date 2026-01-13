<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use App\Status;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Storage;

class GeneratePdfController extends Controller
{
    const COMPANY = [
        'name' => 'SAS ORTHOMASTER',
        'address' => '10 rue Saint-Florent',
        'postal_code' => '67200',
        'city' => 'STRASBOURG',
        'phone' => '+33 (0)7 68 25 06 16',
        'email' => 'info@orthomaster.fr',
        'website' => 'http://orthomaster.fr',
        'siret' => '90918632200014',
        'tva' => 'FR70909186322',
        'formation_number' => '44670716567',
        'qualiopi' => '314178/r2',
        'accounting' => '0388126989',
        'signature' => 'https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/logo-v2/pdf/cachet.png',
        'vendor' => 'ORTHOMASTER',
        'terms_url' => 'https://www.orthomaster.fr/terms-conditions',
    ];

    public function paymentInvoice(Invoice $invoice)
    {
        $cart = Cart::where('invoice_id', $invoice->id)
            ->with(['items', 'items.package', 'members',])
            ->first();

        $data = [
            'company' => $this::COMPANY,
            'invoice' => $invoice,
            'members' => $cart->members,
            'items' => $cart->items
        ];

        $pdf = Pdf::loadView('pdf.payment_invoice', $data);
        $pdf->setOption('isPhpEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setPaper('A4', 'portrait');


        // Create file name unique
        $fileName = 'factures/' . $invoice->no . '.pdf';

        // Upload S3
        $path = Storage::disk('s3')->put($fileName, $pdf->output());
        if ($path) {
            $url = Storage::disk('s3')->url($fileName);
            $invoice->document = $url;
            $invoice->save();
        }

        return true;
    }

    public function quotationTemp(
        $cartId,
        $qNo,
        $qDate,
        $qExpDate,
        $client,
        $qRangeDate,
        $organizationName,
        $members,
        $items,
        $unitPrice,
        $tva,
        $durationTotal,
        $signatureLink = null,
        $signatory = null,
    ) {
        $amountHT = round($unitPrice * $durationTotal / 60, 2);
        $tvaAmount = round(round($unitPrice * $durationTotal / 60, 2) * $tva / 100, 2);

        $data = [
            'company' => $this::COMPANY,
            'no' => $qNo,
            'date' => $qDate,
            'exp_date' => $qExpDate,
            'client' => $client,
            'rangeDate' => $qRangeDate,
            'organizationName' => $organizationName,
            'members' => $members,
            'items' => $items,
            'unitPrice' => $unitPrice,
            'amount_ht' => $amountHT,
            'tva' => $tva,
            'tva_amount' => $tvaAmount,
            'amount' => $amountHT + $tvaAmount,
            'signatureLink' => $signatureLink,
            'signatory' => $signatory
        ];

        if ($signatureLink) {
            $pdf = Pdf::loadView('pdf.quotation_signed', $data);
            $fileName = 'quotations_signed/' . $cartId . '/' . $qNo . '.pdf';
        } else {
            $pdf = Pdf::loadView('pdf.quotation_unsigned', $data);
            $fileName = 'quotations_unsigned/' . $cartId . '/' . $qNo . '.pdf';
        }

        $pdf->setOption('isPhpEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setPaper('A4', 'portrait');

        // Upload S3
        $path = Storage::disk('s3')->put($fileName, $pdf->output());
        if ($path) {
            $url = Storage::disk('s3')->url($fileName);
            return $url;
        }

        return false;
    }


    public function programFilePdf($cartId, $signatureLink, $companyName, $name, $location, $date)
    {
        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile(storage_path('app/samples/program.pdf'));
            $name = mb_convert_encoding($name, 'UTF-8', 'auto');

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $pdf->AddPage();
                $pdf->useTemplate($templateId);

                if ($pageNo == 8) {
                    $pdf->SetFont('Helvetica', '', 12);
                    $pdf->SetDrawColor(255, 0, 0); // RGB

                    // Company
                    $pdf->SetXY(38, 207);
                    $pdf->Write(0, $companyName);

                    // Name
                    $pdf->SetXY(80, 215);
                    $pdf->Write(0, $name);

                    // City
                    $pdf->SetXY(24, 238);
                    $pdf->Write(0, $location);

                    // Date
                    $pdf->SetXY(78, 238);
                    $pdf->Write(0, $date);

                    // Signature
                    $pdf->Image($signatureLink, 100, 240, 60);
                }
            }

            $pdfContent = $pdf->Output('', 'S');

            // Create file name
            $fileName = 'quotations_signed/' . $cartId . '/programme.pdf';
            $success = Storage::disk('s3')->put($fileName, $pdfContent, 'public');;
            if ($success) {
                $url = Storage::disk('s3')->url($fileName);
                return $url;
            }

            return false;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
