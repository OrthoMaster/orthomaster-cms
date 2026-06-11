<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Contract;
use App\Models\FundingOrganization;
use App\Models\Invoice;
use App\Models\User;
use App\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GenerateDocumentController extends Controller
{
    public function generateContractView()
    {
        return view('generate.contract');
    }

    public function generateContract(Request $request)
    {
        try {
            // Get from request
            $date = $request->date ? \Carbon\Carbon::parse($request->date) : now();
            $signatureLink = $request->signatureLink;
            $signatory = $request->signatory;
            $userId = $request->userId;

            $cart = Cart::findOrFail($request->cartId);
            $cart->load(['members', 'items', 'items.package']);

            $user = User::find($cart->user_id);
            $company = $user->companiesUserIsManager()->first();

            $cartNo = str_pad($cart->id, 3, '0', STR_PAD_LEFT);

            $generatePdfController = new GeneratePdfController();

            if ($cart->payment_type != '2') {
                return back()->withInputs()->with([
                    'message' => "Not FIFPL",
                    'alert-type' => 'error'
                ]);
            }

            $organization = FundingOrganization::find($cart->payment_type);
            if (!$organization) {
                return back()->withInputs()->with([
                    'message' => "No organization",
                    'alert-type' => 'error'
                ]);
            }

            // FIFPL - Each person 1 contract
            if ($cart->payment_type == '2') {
                $itemGroups = $cart->groupItemsByDurationBestFit(Organization::FIFPL_MAX_DURATION->value);
                $contractTotal = sizeof($itemGroups);

                foreach ($cart->members as $user) {
                    if ($user->id == $userId) {
                        $userNo = str_pad($user->id, 3, '0', STR_PAD_LEFT);

                        foreach ($itemGroups as $key => $group) {
                            $qNo = 'D-' . $date->format('ym') . '-' . $organization->code . '-' . $userNo . '-' . $cartNo . ($contractTotal > 1 ? '_' . ($key + 1) : '');
                            return $generatePdfController->quotationTemp(
                                $cart->id,
                                $qNo,
                                $date->format('d/m/Y'),
                                $date->copy()->addMonth()->format('d/m/Y'),
                                $company,
                                $date->copy()->addMonth()->format('d/m/Y') . ' - ' . $date->copy()->addMonth(2)->format('d/m/Y'),
                                $organization->id,
                                [$user],
                                $group,
                                Organization::FIFPL_UNIT_PRICE->value,
                                (float) setting('system.tva'),
                                $cart->duration_total_days,
                                $signatureLink,
                                $signatory,
                                true
                            );
                        }
                    }
                }
            }

            return back()->withInputs()->with([
                'message' => "Success",
                'alert-type' => 'info'
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['generate-document', 'contract']);
            return back()->withInputs()->with([
                'message' => 'Error',
                'alert-type' => 'error'
            ]);
        }
    }

    public function generateInvoice(Request $request)
    {
        try {
            $generatePdfController = new GeneratePdfController();

            $cartId = $request->cartId;
            $userId = $request->userId;
            // $date = $request->date ? \Carbon\Carbon::parse($request->date) : null;
            
            $cart = Cart::findOrFail($cartId);
            $cart->load(['members', 'items', 'items.package']);

            $member = User::findOrFail($userId);
            $memberContract = Contract::where('cart_id', $cart->id)
                ->where('beneficiary_id', $userId)
                ->with('company')
                ->first();
            $invoice = Invoice::findOrFail($memberContract->invoice_id);

            return $generatePdfController->fifplPaymentInvoice(
                $invoice->no,
                $memberContract->no,
                $memberContract->created_at->addMonth()->format('d/m/Y'),
                $memberContract->created_at->addMonth(2)->format('d/m/Y'),
                $memberContract->created_at->addMonth()->format('d/m/Y') . ' - ' . $memberContract->created_at->addMonth(2)->format('d/m/Y'),
                $memberContract->company,
                $member,
                $cart->items,
                Organization::FIFPL_UNIT_PRICE->value,
                (float) setting('system.tva'),
                $cart->duration_total_days,
                $invoice->paid_at ? $invoice->paid_at->format('d/m/Y') : null,
                true
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['generate-document', 'invoice']);
            return back()->withInputs()->with([
                'message' => 'Error',
                'alert-type' => 'error'
            ]);
        }
    }
}
