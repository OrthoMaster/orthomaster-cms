<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use App\Status;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Tcpdf\Fpdi;
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
        'rib' => '14707 50004 33021784328 55',
        'iban' => 'FR76 1470 7500 0433 0217 8432 855',
        'bic' => 'CCBPFRPPMTZ',
        'domi' => 'BP ALSACE LORRAINE CHAMPAGNE',
        'agent' => 'BOULEVARD D ANVERS'
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
        $organizationId,
        $members,
        $items,
        $unitPrice,
        $tva,
        $durationTotal,
        $signatureLink = null,
        $signatory = null,
    ) {
        if ($organizationId == '2') {
            $amountHT = round($unitPrice * $durationTotal, 2);
            $tvaAmount = round(round($unitPrice * $durationTotal, 2) * $tva / 100, 2);
        } else {
            $amountHT = round($unitPrice * $durationTotal / 60, 2);
            $tvaAmount = round(round($unitPrice * $durationTotal / 60, 2) * $tva / 100, 2);
        }


        $data = [
            'company' => $this::COMPANY,
            'no' => $qNo,
            'date' => $qDate,
            'exp_date' => $qExpDate,
            'client' => $client,
            'rangeDate' => $qRangeDate,
            'organizationId' => $organizationId,
            'members' => $members,
            'items' => $items,
            'unitPrice' => $unitPrice,
            'durationTotal' => $durationTotal,
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

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $pdf->AddPage();
                $pdf->useTemplate($templateId);

                if ($pageNo == 8) {
                    $pdf->SetFont('Helvetica', '', 12);
                    $pdf->SetDrawColor(255, 0, 0); // RGB

                    // Company
                    $pdf->SetXY(38, 204.5);
                    $pdf->Write(0, $companyName);

                    // Name
                    $pdf->SetXY(80, 212);
                    $pdf->Write(0, $name);

                    // City
                    $pdf->SetXY(24, 235.2);
                    $pdf->Write(0, $location);

                    // Date
                    $pdf->SetXY(78, 235.2);
                    $pdf->Write(0, $date);

                    // Signature
                    $pdf->Image($signatureLink, 100, 237, 45, 45);
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

    public function fifplPaymentInvoice(
        $invNo,
        $qNo,
        $qDate,
        $qExpDate,
        $qRangeDate,
        $client,
        $member,
        $items,
        $unitPrice,
        $tva,
        $durationTotal,
        $paidDate,
    ) {
        $amountHT = round($unitPrice * $durationTotal, 2);
        $tvaAmount = round(round($unitPrice * $durationTotal, 2) * $tva / 100, 2);

        $data = [
            'company' => $this::COMPANY,
            'invNo' => $invNo,
            'qNo' => $qNo,
            'date' => $qDate,
            'exp_date' => $qExpDate,
            'rangeDate' => $qRangeDate,
            'client' => $client,
            'member' => $member,
            'items' => $items,
            'unitPrice' => $unitPrice,
            'durationTotal' => $durationTotal,
            'amount_ht' => $amountHT,
            'tva' => $tva,
            'tva_amount' => $tvaAmount,
            'amount' => $amountHT + $tvaAmount,
            'paidDate' => $paidDate
        ];



        $pdf = Pdf::loadView('pdf.fifpl_invoice', $data);
        $fileName = 'factures/' . str_replace('/', '_', $invNo) . '.pdf';

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

    public function fifplCertificate($userId, $name, $fromDate, $toDate, $packages, $totalMins, $totalPackages, $amountHT, $amount)
    {
        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile(storage_path('app/fifpl/certificate.pdf'));
            for ($i = 1; $i <= $pageCount; $i++) {
                $templateId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                if ($i == 1) {
                    $pdf->SetDrawColor(255, 0, 0); // RGB

                    // Name
                    $pdf->SetFont('Helvetica', '', 15);
                    $pdf->SetXY(65, 99);
                    $pdf->Write(0, $name);

                    foreach ($packages as $key => $package) {
                        $pdf->SetFont('Helvetica', '', mb_strlen($package->package->name) > 30 ? 7.5 : 8);
                        switch ($key) {
                            case 0:
                                $pdf->SetXY(15, 111);
                                break;
                            case 1:
                                $pdf->SetXY(15, 115);
                                break;
                            case 2:
                                $pdf->SetXY(62, 111);
                                break;
                            case 3: 
                                $pdf->SetXY(62, 115);
                                break;
                            case 4: 
                                $pdf->SetXY(108, 111);
                                break;
                            case 5: 
                                $pdf->SetXY(108, 115);
                                break;
                            case 6: 
                                $pdf->SetXY(152, 115);
                                break;
                        }

                        $pdf->Write(0, $package->package->name);
                    }

                    // Date
                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(103, 121);
                    $pdf->Write(0, $fromDate->format('d      m      Y'));
                    $pdf->SetXY(156, 121);
                    $pdf->Write(0, $toDate->format('d      m      Y'));

                    // Training Info
                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(65, 178.5);
                    $pdf->Write(0, number_format($totalMins / 60, 2, '.', ' '));

                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(115, 178.5);
                    $pdf->Write(0, $totalPackages);

                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(155, 178.5);
                    $pdf->Write(0, $toDate->format('d / m / Y'));

                    // Price
                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(70, 210.5);
                    $pdf->Write(0, number_format($amountHT, 2, '.', ''));

                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(115, 210.5);
                    $pdf->Write(0, number_format($amount, 2, '.', ''));

                    // Sign date
                    $pdf->SetFont('Helvetica', '', 12);
                    $pdf->SetXY(27, 242);
                    $pdf->Write(0, $toDate->format('d / m / Y'));
                }
            }

            $pdfContent = $pdf->Output('', 'S');

            // Create file name
            $fileName = 'certifications/' . $userId . '/certif-' . $toDate->format('YmdHi') . '.pdf';
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

    public function fifplCertificate2()
    {
        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile(storage_path('app/fifpl/certificate.pdf'));
            for ($i = 1; $i <= $pageCount; $i++) {
                $templateId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                if ($i == 1) {
                    $pdf->SetDrawColor(255, 0, 0); // RGB

                    // Name
                    $pdf->SetFont('Helvetica', '', 14);
                    $pdf->SetXY(65, 99);
                    $pdf->Write(0, 'Lê François œ ç');

                    // Packages
                    $pdf->SetFont('Helvetica', '', 7.5);
                    $pdf->SetXY(15, 111);
                    $pdf->Write(0, 'Membre Inférieur / Matériel médical');

                    $pdf->SetFont('Helvetica', '', 7.5);
                    $pdf->SetXY(62, 111);
                    $pdf->Write(0, 'Membre Inférieur / Matériel médical');

                    $pdf->SetFont('Helvetica', '', 7.5);
                    $pdf->SetXY(108, 111);
                    $pdf->Write(0, 'Membre Inférieur / Matériel médical');

                    $pdf->SetFont('Helvetica', '', 7.5);
                    $pdf->SetXY(152, 111);
                    $pdf->Write(0, 'Membre Inférieur / Matériel médical');


                    $pdf->SetFont('Helvetica', '', 8);
                    $pdf->SetXY(15, 115);
                    $pdf->Write(0, 'Rachis Lombaire et Cervical');

                    $pdf->SetFont('Helvetica', '', 8);
                    $pdf->SetXY(65, 115);
                    $pdf->Write(0, 'Rachis Lombaire et Cervical');

                    $pdf->SetFont('Helvetica', '', 8);
                    $pdf->SetXY(115, 115);
                    $pdf->Write(0, 'Rachis Lombaire et Cervical');

                    // Date
                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(103, 121);
                    $pdf->Write(0, '05      07      2026');
                    $pdf->SetXY(156, 121);
                    $pdf->Write(0, '05      07      2026');

                    // Training Info
                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(65, 178.5);
                    $pdf->Write(0, '12');

                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(115, 178.5);
                    $pdf->Write(0, '12');

                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(155, 178.5);
                    $pdf->Write(0, '12 / 04 / 2026');

                    // Price
                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(70, 210.5);
                    $pdf->Write(0, '1000.00');

                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(115, 210.5);
                    $pdf->Write(0, '1000.00');

                    // Sign date
                    $pdf->SetFont('Helvetica', '', 12);
                    $pdf->SetXY(27, 242);
                    $pdf->Write(0, '12 / 04 / 2026');
                }
            }

            $pdfContent = $pdf->Output('', 'S');

            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="certificate.pdf"')
                ->header('Content-Length', strlen($pdfContent));
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
