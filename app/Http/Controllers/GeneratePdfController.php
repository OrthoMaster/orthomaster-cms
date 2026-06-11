<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Package;
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
        $exportPdf = false
    ) {
        if ($organizationId == '2') {
            $amountHT = round($unitPrice * $durationTotal, 2);
            $tvaAmount = round(round($unitPrice * $durationTotal, 2) * $tva / 100, 2);
        } else {
            $amountHT = round($unitPrice * $durationTotal / 60 * sizeof($members), 2);
            $tvaAmount = round(round($unitPrice * $durationTotal / 60 * sizeof($members), 2) * $tva / 100, 2);
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

        // Export pdf for generate/test
        if ($exportPdf) {
            return $pdf->download($qNo . '-' . now()->format('YmdHi') . '.pdf');
        }

        // Upload S3
        $path = Storage::disk('s3')->put($fileName, $pdf->output());
        if ($path) {
            $url = Storage::disk('s3')->url($fileName);
            return $url;
        }

        return false;
    }

    public function autoProgFilePdf($cartId, $signatureLink = '', $companyName = '', $name = '', $location = '', $date = '', $exportPdf = false)
    {
        // Get training list with cartId
        $cart = Cart::with([
            'items',
            'items.package',
            'items.package.modules',
            'items.package.modules.lessons'
        ])->find($cartId);

        $totalModules = 0;
        if ($cart) {
            $totalModules = $cart->items->sum(function ($item) {
                return $item->package->modules->count();
            });
        }

        $data = [
            'company' => $this::COMPANY,
            'signatureLink' => $signatureLink,
            'companyName' => $companyName,
            'name' => $name,
            'location' => $location,
            'date' => $date,
            'cart' => $cart,
            'totalModules' => $totalModules
        ];

        $pdf = Pdf::loadView('pdf.auto_programme', $data);
        $pdf->setOption('isPhpEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setPaper('A4', 'portrait');

        if ($exportPdf) {
            return $pdf->stream('programme-' . now()->format('YmdHi') . '.pdf');
        }

        // Upload S3
        $fileName = 'quotations_signed/' . $cartId . '/programme-' . now()->format('YmdHi') . '.pdf';
        $path = Storage::disk('s3')->put($fileName, $pdf->output());
        if ($path) {
            $url = Storage::disk('s3')->url($fileName);
            return $url;
        }

        return false;
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
        $exportPdf = false
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

        // Export pdf for generate/test
        if ($exportPdf) {
            return $pdf->download(str_replace('/', '_', $invNo) . '-' . now()->format('YmdHi') . '.pdf');
        }

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
                        $pdf->SetFont('Helvetica', '', 8);
                        switch ($key) {
                            case 0:
                                $pdf->SetXY(15, 111);
                                break;
                            case 1:
                                $pdf->SetXY(15, 115);
                                break;
                            case 2:
                                $pdf->SetXY(108, 111);
                                break;
                            case 3:
                                $pdf->SetXY(108, 115);
                                break;
                            case 4:
                                $pdf->SetXY(15, 119);
                                break;
                        }

                        $pdf->Write(0, $package->package->fifpl_cert_name);
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

    public function testGenerateFifplCert()
    {
        $name = 'Debard Lydie';
        $packages = Package::whereIn('id', [3])->get();
        $packages[0]->fifpl_cert_name = "Les Bases de l'Appareillage Orthopédique de Série";
        $startDate = '01      01      2026';
        $endDate = '31      01      2026';
        $totalHour = '12.00';
        $endDate2 = '31 / 01 / 2026';
        $price1 = '300.00';
        $price2 = '360.00';


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

                    // Packages
                    foreach ($packages as $key => $package) {
                        $pdf->SetFont('Helvetica', '', 8);
                        switch ($key) {
                            case 0:
                                $pdf->SetXY(15, 111);
                                break;
                            case 1:
                                $pdf->SetXY(15, 115);
                                break;
                            case 2:
                                $pdf->SetXY(108, 111);
                                break;
                            case 3:
                                $pdf->SetXY(108, 115);
                                break;
                            case 4:
                                $pdf->SetXY(15, 119);
                                break;
                        }

                        $pdf->Write(0, $package->fifpl_cert_name);
                    }

                    // Date
                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(103, 121);
                    $pdf->Write(0, $startDate);
                    $pdf->SetXY(156, 121);
                    $pdf->Write(0, $endDate);

                    // Training Info
                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(65, 178.5);
                    $pdf->Write(0, $totalHour);

                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(115, 178.5);
                    $pdf->Write(0, sizeof($packages));

                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(155, 178.5);
                    $pdf->Write(0, $endDate2);

                    // Price
                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(70, 210.5);
                    $pdf->Write(0, $price1);

                    $pdf->SetFont('Helvetica', '', 10);
                    $pdf->SetXY(115, 210.5);
                    $pdf->Write(0, $price2);

                    // Sign date
                    $pdf->SetFont('Helvetica', '', 12);
                    $pdf->SetXY(27, 242);
                    $pdf->Write(0, $endDate2);
                }
            }

            $pdfContent = $pdf->Output('', 'S');

            $fileName = 'certif-' . now()->format('YmdHi') . '.pdf';

            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $fileName . '"')
                ->header('Content-Length', strlen($pdfContent));
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // New program
    public function testAutotProgFilePdf()
    {
        return $this->autoProgFilePdf(
            400,
            'https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/signatures/2026/04/14/28cd1fd1-bccc-4f8e-8fe3-9e61381cb094.png',
            'CompanyName',
            'Name',
            'Strasbourg',
            '11/01/2022',
            true
        );

        return $this->autoProgFilePdf(400);
    }
}
