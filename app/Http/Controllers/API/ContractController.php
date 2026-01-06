<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\GeneratePdfController;
use App\Models\Cart;
use App\Models\FundingOrganization;
use App\Models\User;
use App\Organization;
use App\Status;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractController extends Controller
{
    const COMMON_PROGRAM_PDF_LINK = 'https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/templates/program.pdf';

    public function generate(Request $request)
    {
        $cart = Cart::findByUser($request->user()->id, null, true);
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => "Le panier d'achat n'existe pas."
            ], 403);
        }

        if (!$cart->contract_creatable) {
            return response()->json([
                'success' => false,
                'message' => "Vous n'êtes pas autorisé à faire un dossier."
            ], 403);
        }

        $reponseData = $this->generateContractsByProCart($request->user()->id);
        return response()->json([
            'success' => true,
            'data' => $reponseData,
        ]);
    }

    public function sign(Request $request)
    {
        $signData = $request->input('signData');

        // Remove base64 prefix if exists
        $base64Image = $signData['signature'];
        if (strpos($base64Image, 'data:image') === 0) {
            $base64Image = preg_replace('/^data:image\/\w+;base64,/', '', $base64Image);
        }

        // Decode base64
        $imageData = base64_decode($base64Image);
        if ($imageData === false) {
            throw new \Exception('Invalid signature data');
        }

        // Generate unique filename
        $filename = 'signatures/' . now()->format('Y/m/d') . '/' . Str::uuid() . '.png';

        // Save to S3 (keeps transparency automatically for PNG)
        Storage::disk('s3')->put($filename, $imageData, 'public');

        // Get public URL
        $signatureUrl = Storage::disk('s3')->url($filename);

        // Create signature failed
        if (!$signatureUrl) {
            return response()->json([
                'success' => false,
            ], 403);
        }

        try {
            // Sign to contract
            $this->generateContractsByProCart($request->user()->id, $signatureUrl, $signData['fullName']);

            // Sign to program
            $generatePdfController = new GeneratePdfController();
            $cart = Cart::findByUser($request->user()->id);
            $user = User::find($request->user()->id);
            $company = $user->companiesUserIsManager()->first();
            $generatePdfController->programFilePdf(
                $cart->id,
                $signatureUrl,
                $company->name,
                $signData['fullName'],
                $signData['location'],
                now()->format('d/m/Y')
            );


            $cart->status = Status::DELETED->value;
            $cart->save();

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['sign-contract']);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue'
            ]);
        }
    }

    private function generateContractsByProCart($userId, $signatureLink = null, $signatory = null)
    {
        try {
            $generatePdfController = new GeneratePdfController();

            $cart = Cart::findByUser($userId, null, true);
            $cart->load(['members', 'items', 'items.package']);

            $user = User::find($userId);
            $company = $user->companiesUserIsManager()->first();

            $cartNo = 'T' . str_pad($cart->id, 4, '0', STR_PAD_LEFT);

            $reponseData = [];

            if ($cart->payment_type == '0') {
                return null;
            }

            $organization = FundingOrganization::find($cart->payment_type);
            if (!$organization) {
                return null;
            }

            // OPCO-EP
            if ($cart->payment_type == '1') {
                $itemGroups = $cart->groupItemsByDurationBestFit(Organization::OPCO_MAX_DURATION->value);
                $contractTotal = sizeof($itemGroups);
                $pdfLinks = [];

                foreach ($itemGroups as $key => $group) {

                    $pdf = $generatePdfController->quotationTemp(
                        $cart->id,
                        $cartNo . '_' . $organization->code . ($contractTotal > 1 ? '_' . ($key + 1) : ''),
                        now()->format('d/m/Y'),
                        now()->addMonth()->format('d/m/Y'),
                        $company,
                        now()->format('d/m/Y') . ' - ' . now()->addMonth()->format('d/m/Y'),
                        $organization->name,
                        $cart->users,
                        $group,
                        Organization::OPCO_UNIT_PRICE->value,
                        (float) setting('system.tva'),
                        $cart->calculateGroupDuration($group),
                        $signatureLink,
                        $signatory
                    );

                    if ($pdf) {
                        array_push($pdfLinks, $pdf);
                    }
                }

                array_push($reponseData, array(
                    'group' => 'Dossiers',
                    'pdfLinks' => $pdfLinks
                ));

                // Add common program pdf 
                array_push($reponseData, array(
                    'group' => 'Programme',
                    'pdfLinks' => [$this::COMMON_PROGRAM_PDF_LINK]
                ));
            }

            // FIFPL - Each person 1 contract
            if ($cart->payment_type == '2') {
                $itemGroups = $cart->groupItemsByDurationBestFit(Organization::FIFPL_MAX_DURATION->value);
                $contractTotal = sizeof($itemGroups);

                foreach ($cart->members as $user) {
                    $pdfLinks = [];

                    foreach ($itemGroups as $key => $group) {
                        $pdf = $generatePdfController->quotationTemp(
                            $cart->id,
                            $cartNo . '_' . $organization->code . '_' . $user->name . '-' . $user->id . ($contractTotal > 1 ? '_' . ($key + 1) : ''),
                            now()->format('d/m/Y'),
                            now()->addMonth()->format('d/m/Y'),
                            $company,
                            now()->addMonth()->format('d/m/Y') . ' - ' . now()->addMonth(2)->format('d/m/Y'),
                            $organization->name,
                            [$user],
                            $group,
                            Organization::FIFPL_UNIT_PRICE->value,
                            (float) setting('system.tva'),
                            $cart->calculateGroupDuration($group),
                            $signatureLink,
                            $signatory
                        );

                        if ($pdf) {
                            array_push($pdfLinks, $pdf);
                        }
                    }

                    // Add pdf
                    array_push($reponseData, array(
                        'group' => $user->name,
                        'pdfLinks' => $pdfLinks
                    ));
                }

                // Add common program pdf 
                array_push($reponseData, array(
                    'group' => 'Programme',
                    'pdfLinks' => [$this::COMMON_PROGRAM_PDF_LINK]
                ));
            }


            if ($cart->payment_type != '1' && $cart->payment_type != '2') {
                $itemGroups = $cart->groupItemsByDurationBestFit();
                $contractTotal = sizeof($itemGroups);

                foreach ($cart->members as $user) {
                    $pdfLinks = [];

                    foreach ($itemGroups as $group) {
                        $pdf = $generatePdfController->quotationTemp(
                            $cart->id,
                            $cartNo . '_' . $user->name . '-' . $user->id,
                            now()->format('d/m/Y'),
                            now()->addMonth()->format('d/m/Y'),
                            $company,
                            now()->format('d/m/Y') . '- ' . now()->addMonth()->format('d/m/Y'),
                            $organization->name,
                            [$user],
                            $group,
                            (float) setting('system.unit_price'),
                            (float) setting('system.tva'),
                            $cart->duration_total,
                            $signatureLink,
                            $signatory
                        );

                        if ($pdf) {
                            array_push($pdfLinks, $pdf);
                        }
                    }

                    // Add pdf
                    array_push($reponseData, array(
                        'group' => $user->name,
                        'pdfLinks' => $pdfLinks
                    ));
                }

                // Add common program pdf 
                array_push($reponseData, array(
                    'group' => 'Programme',
                    'pdfLinks' => [$this::COMMON_PROGRAM_PDF_LINK]
                ));
            }

            return $reponseData;
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['sign-contract']);
            throw $e;
        }
    }
}
