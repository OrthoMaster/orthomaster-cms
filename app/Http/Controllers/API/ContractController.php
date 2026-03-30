<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\GeneratePdfController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\NotificationController;
use App\Models\Cart;
use App\Models\Contract;
use App\Models\FundingOrganization;
use App\Models\User;
use App\Organization;
use App\Status;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

            $cart = Cart::find($signData['cartId']);
            $user = User::find($request->user()->id);
            $company = $user->companiesUserIsManager()->first();

            // Sign to program
            $generatePdfController = new GeneratePdfController();
            $pdfUrl = $generatePdfController->programFilePdf(
                $cart->id,
                $signatureUrl,
                $company->name,
                $signData['fullName'],
                $signData['location'],
                now()->format('d/m/Y')
            );
            if ($pdfUrl) {
                $cartNo = str_pad($cart->id, 3, '0', STR_PAD_LEFT);

                Contract::updateOrCreate(
                    [
                        'no' => 'Programme_' . now()->format('ym') . '-' . $cartNo,
                        'cart_id' => $cart->id,
                    ],
                    [
                        'document' => $pdfUrl,
                        'company_id' => $company->id,
                        'created_by' => $request->user()->id,
                        'beneficiary_id' => $request->user()->id,
                        'type' => 'programme',
                        'status' => Status::ACTIVE->value
                    ]
                );
            }

            // Active packages - only OPCO-EP and FIFPL
            if ($cart->payment_type == '1' || $cart->payment_type == '2') {
                $itemIds = $cart->items()->pluck('package_id')->toArray();
                foreach ($cart->members as $member) {
                    $member->packages()->syncWithoutDetaching($itemIds);
                }

                // Notification
                $cartPaymentType = $cart->payment_type;
                $cartMemberIds = $cart->member_ids;
                $cartUserId = $cart->user_id;
                dispatch(function () use ($cartPaymentType, $cartMemberIds, $cartUserId) {
                    $notificationController = new \App\Http\Controllers\NotificationController(
                        app(\App\Services\ExpoNotificationService::class)
                    );
                    if ($cartPaymentType == '2') { // FIFPL
                        $notificationController->receiveNewContract($cartMemberIds);
                    } else {
                        $notificationController->receiveNewContract([$cartUserId]);
                    }
                    $notificationController->activeNewPackage($cartMemberIds);
                })->afterResponse();
            }
            $cart->status = Status::DELETED->value; // block Cart
            $cart->save();

            // Send email
            $leaderId = $request->user()->id;
            $leaderEmail = $request->user()->email;

            // FIFPL - Send to leader and members
            if ($cart->payment_type == '2') {
                foreach ($cart->members as $member) {
                    $memberId = $member->id;
                    $memberEmail = $member->email;

                    $cartId = $cart->id;
                    $cartPaymentType = $cart->payment_type;
                    dispatch(function () use ($memberId, $memberEmail, $cartId, $cartPaymentType) {
                        Mail::to($memberEmail)
                            ->send(new \App\Mail\SignedContractMail($memberId, $cartId, $cartPaymentType));
                    })->afterResponse();
                }
            } else {
                $cartId = $cart->id;
                $cartPaymentType = $cart->payment_type;
                dispatch(function () use ($leaderId, $leaderEmail, $cartId, $cartPaymentType) {
                    Mail::to($leaderEmail)
                        ->send(new \App\Mail\SignedContractMail($leaderId, $cartId, $cartPaymentType));
                })->afterResponse();
            }

            // Notification to admins
            $cartId = $cart->id;
            dispatch(function () use ($cartId) {
                MailController::hasNewSignedContract($cartId);
            })->afterResponse();

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

            $cartNo = str_pad($cart->id, 3, '0', STR_PAD_LEFT);
            $companyNo = str_pad($company->id, 3, '0', STR_PAD_LEFT);

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
                    $qNo = 'D-' . now()->format('ym') . '-' . $organization->code . '-' . $companyNo . '-' . $cartNo . ($contractTotal > 1 ? '_' . ($key + 1) : '');
                    $pdf = $generatePdfController->quotationTemp(
                        $cart->id,
                        $qNo,
                        now()->format('d/m/Y'),
                        now()->addMonth()->format('d/m/Y'),
                        $company,
                        now()->format('d/m/Y') . ' - ' . now()->addMonth()->format('d/m/Y'),
                        $organization->id,
                        $cart->members,
                        $group,
                        Organization::OPCO_UNIT_PRICE->value,
                        (float) setting('system.tva'),
                        $cart->calculateGroupDuration($group),
                        $signatureLink,
                        $signatory
                    );

                    if ($pdf) {
                        array_push($pdfLinks, $pdf);

                        // Signed
                        if ($signatory) {
                            $this->storeSignedContract($qNo, $cart->id, $pdf, $company->id, $organization->name, $userId, $userId, 'signed', Status::ACTIVE->value);
                        }
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
                    $userNo = str_pad($user->id, 3, '0', STR_PAD_LEFT);

                    foreach ($itemGroups as $key => $group) {
                        $qNo = 'D-' . now()->format('ym') . '-' . $organization->code . '-' . $userNo . '-' . $cartNo . ($contractTotal > 1 ? '_' . ($key + 1) : '');
                        $pdf = $generatePdfController->quotationTemp(
                            $cart->id,
                            $qNo,
                            now()->format('d/m/Y'),
                            now()->addMonth()->format('d/m/Y'),
                            $company,
                            now()->addMonth()->format('d/m/Y') . ' - ' . now()->addMonth(2)->format('d/m/Y'),
                            $organization->id,
                            [$user],
                            $group,
                            Organization::FIFPL_UNIT_PRICE->value,
                            (float) setting('system.tva'),
                            $cart->duration_total_days,
                            $signatureLink,
                            $signatory
                        );

                        if ($pdf) {
                            array_push($pdfLinks, $pdf);

                            // Signed
                            if ($signatory) {
                                $this->storeSignedContract($qNo, $cart->id, $pdf, $company->id, $organization->name, $userId, $user->id, 'signed', Status::ACTIVE->value);
                            }
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

            // Other organizations
            if ($cart->payment_type != '1' && $cart->payment_type != '2') {
                $itemGroups = $cart->groupItemsByDurationBestFit();
                $contractTotal = sizeof($itemGroups);

                foreach ($cart->members as $user) {
                    $pdfLinks = [];
                    $userNo = str_pad($user->id, 3, '0', STR_PAD_LEFT);

                    foreach ($itemGroups as $group) {
                        $qNo = 'D-' . now()->format('ym') . '-' . $organization->code . '-' . $userNo . '-' . $cartNo;

                        $pdf = $generatePdfController->quotationTemp(
                            $cart->id,
                            $qNo,
                            now()->format('d/m/Y'),
                            now()->addMonth()->format('d/m/Y'),
                            $company,
                            now()->format('d/m/Y') . '- ' . now()->addMonth()->format('d/m/Y'),
                            $organization->id,
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

                            if ($signatory) {
                                $this->storeSignedContract($qNo, $cart->id, $pdf, $company->id, $organization->name, $userId, $userId, 'signed', Status::ACTIVE->value);
                            }
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

    private function storeSignedContract($no, $cartId, $document, $companyId, $organizationName, $createdById, $beneficiaryId, $type, $status)
    {
        return Contract::updateOrCreate(
            [
                'no' => $no,
                'cart_id' => $cartId,
            ],
            [
                'document' => $document,
                'company_id' => $companyId,
                'organization' => $organizationName,
                'created_by' => $createdById,
                'type' => $type,
                'status' => $status,
                'beneficiary_id' => $beneficiaryId,
            ]
        );
    }
}
