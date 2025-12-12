<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;

class QuotationController extends Controller
{
    public function generateSample()
    {
        $data = $this->getSampleData();

        // $pdf = Pdf::loadView('pdf.quotation', $data);
        $pdf = Pdf::loadView('pdf.quotation_signed', $data);
        $pdf->setOption('isPhpEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('Devis_Sample.pdf');
    }

    /**
     * Get sample data for testing
     * 
     * @return array
     */
    private function getSampleData(): array
    {
        return [
            'company' => [
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
            ],
            'client' => [
                'name' => 'PHARMACIE DE LA RIVIERE',
                'address' => '37 RUE JEAN JAURES',
                'postal_code' => '10440',
                'city' => 'LA RIVIERE-DE-CORPS',
                'siret' => '71797579448',
            ],
            'contract_number' => 'S00140',
            'contract_date' => '09/10/2025',
            'due_date' => '21/05/2026',
            'formation_dates' => '22/10/2025 - 22/11/2025',
            'learners' => [
                'Arnaud COMTE-CARBALLO',
                'Elodie COMTE-CARBALLO',
            ],
            'items' => [
                [
                    'description' => 'Formation "Les Bases de l\'Appareillage Orthopédique de Série" FIFPL',
                    'quantity' => 2.00,
                    'unit' => 'Unité(s)',
                    'unit_price' => 300.00,
                    'tva_rate' => 20,
                    'amount' => 600.00,
                ],
                [
                    'description' => 'Formation "Les Bases de l\'Appareillage Orthopédique de Série" FIFPL',
                    'quantity' => 2.00,
                    'unit' => 'Unité(s)',
                    'unit_price' => 300.00,
                    'tva_rate' => 20,
                    'amount' => 600.00,
                ],
            ],
            'subtotal' => 600.00,
            'tva_amount' => 120.00,
            'total' => 720.00,
            'tva_rate' => 20,
            'signatory' => [
                'signature' => 'https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/logo-v2/pdf/cachet.png',
                'name' => 'Nguyen',
                'first_name' => 'Thanh Tien'
            ]
        ];
    }


    public function addSignature()
    {
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile(storage_path('app/samples/program.pdf'));

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $pdf->AddPage();
            $pdf->useTemplate($templateId);

            if ($pageNo == 8) {
                $pdf->SetFont('Arial', '', 12);
                $pdf->SetDrawColor(255, 0, 0); // RGB

                // Company
                $pdf->SetXY(38, 207);
                $pdf->Write(0, 'Pharmacie A');

                // Name
                $pdf->SetXY(80, 215);
                $pdf->Write(0, 'NGUYEN THANH TIEN');

                // City
                $pdf->SetXY(24, 238);
                $pdf->Write(0, 'Strasbourg');

                // Date
                $pdf->SetXY(78, 238);
                $pdf->Write(0, '15/12/2025');

                // Signature
                $pdf->Image('https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/logo-v2/pdf/cachet.png', 100, 240, 80);
            }
        }
        $pdf->Output('F', public_path('test.pdf'));
    }
}
