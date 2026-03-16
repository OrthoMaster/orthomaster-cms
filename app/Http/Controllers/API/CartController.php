<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\FundingOrganization;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Training;
use App\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    public function getDetails(Request $request)
    {
        try {
            $cart = Cart::findByUser($request->user()->id);
            if (!$cart) {
                return response()->json([
                    'success' => false,
                    'data' => null
                ]);
            }

            $cart->load('items.package');
            $cart->updateTotalDuration();
            $cart->estimatePriceTotal();

            return response()->json([
                'success' => true,
                'data' => $cart
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['cart', $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addPackage($id, Request $request)
    {
        $package = Package::findById($id);
        if (!$package) {
            return response()->json([
                'success' => false,
            ], 404);
        }

        try {
            // Check addable - not in selected packages
            $user = $request->user();

            // Check student
            if ($user->student_flg) {
                return response()->json([
                    'success' => false,
                ], 409);
            }
            if (!$user->student_flg) {
                $packageIds = $user->packages->pluck('packages.id')->toArray();
                if (in_array($package->id, $packageIds)) {
                    return response()->json([
                        'success' => false,
                    ], 409);
                }
            }

            // Add item to cart
            $cart = Cart::findByUser($user->id);
            $cart->items()->updateOrCreate(['package_id' => $package->id]);

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['cart', 'add-package'], $request->user()->id);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function removePackage($id, Request $request)
    {
        try {
            $cart = Cart::findByUser($request->user()->id);
            $cart->items()->where('package_id', $id)->delete();

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['cart', 'remove-package', $request->user()->id]);
            return response()->json([
                'success' => false,
            ], 500);
        }
    }

    public function clear(Request $request)
    {
        try {
            $cart = Cart::findByUser(Auth::user()->id, $request->cartId);
            $cart->items()->delete();
            $cart->members()->delete();

            if ($cart->invoice_id) {
                $invoice = Invoice::find($cart->invoice_id);
                if ($invoice) {
                    $invoice->delete();
                }
            }

            $cart->invoice_id = null;
            $cart->save();

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['cart', 'clear-package', $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function complete($invoiceId)
    {
        // Disable cart to sync and create invoice paper
        $cart = Cart::where('invoice_id', $invoiceId)->first();

        if (!$cart) {
            return false;
        }
        $cart->status = Status::DELETED->value;
        $cart->save();

        
        $itemIds = $cart->items()->pluck('package_id')->toArray();
        if (sizeof($cart->members) <= 0) {
            $user = $cart->user;
            $cart->members()->syncWithoutDetaching([$user->id]);

            // Active packages for user
            $user->packages()->syncWithoutDetaching($itemIds);
        } else {
            foreach ($cart->members as $user) {
                $user->packages()->syncWithoutDetaching($itemIds);
            }
        }

        // Notification
        dispatch(function () use ($cart) {
            (new \App\Http\Controllers\NotificationController(app(\App\Services\ExpoNotificationService::class)))
                ->activeNewPackage($cart->member_ids);
        })->afterResponse();

        return true;
    }

    /**
     * Get payment methods base on members of company
     */
    public function getPaymentMethods(Request $request)
    {
        $company = $request->user()->companiesUserIsManager()->first();
        if (!$company) {
            return response()->json([
                'success' => false,
            ], 403);
        }

        $organizationIds = $company->getMemberOrganizationIds();

        $methods = array([
            'id' => '0',
            'title' => 'Paiment par Carte',
            'description' => 'Paiement immédiat par carte bancaire',
        ]);

        foreach ($organizationIds as $id) {
            if ($id == 1) { // OPCO-EP
                array_push($methods, [
                    'id' => $id,
                    'title' => 'Faire dossier OPCO-EP',
                    'description' => 'Financement par OPCO-EP',
                    'terms' => [
                        'Documents requis : convention de formation (devis + programme signés)',
                        'Durée minimum du programme : 4 heures',
                    ]
                ]);
            }

            if ($id == 2) { // FIFPL
                array_push($methods, [
                    'id' => $id,
                    'title' => 'Faire dossier FIFPL',
                    'description' => 'Financement par FIFPL',
                    'terms' => [
                        'Documents requis : convention de formation (devis signé + programme)',
                        'Durée minimum du programme : 3 heures',
                    ]
                ]);
            }

            if ($id != 1 && $id != 2) {
                $organization = FundingOrganization::find($id);
                if ($organization) {
                    array_push($methods, [
                        'id' => $id,
                        'title' => 'Faire dossier ' . $organization->name,
                        'description' => $organization->description,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $methods
        ]);
    }

    /**
     * Details of cart from company espace
     */
    public function getDetailsProCart(Request $request)
    {
        try {
            $cart = Cart::findByUser($request->user()->id, null, true);
            if (!$cart) {
                return response()->json([
                    'success' => false,
                    'data' => null
                ]);
            }

            $company = $request->user()->companiesUserIsManager()->first();
            if (!$company) {
                return response()->json([
                    'success' => false,
                    'data' => null
                ]);
            }

            $members = null;
            if ($request->payment_method == '0' || $request->payment_method == 'card') {
                $cart->payment_type = 0;
                $members = $company->users;
            } else {
                $cart->payment_type = $request->payment_method;
                $members = $company->getMemberByOrganization($request->payment_method);
            }

            $cart->save();
            $cart->load('items.package');
            $cart->updateTotalDuration();
            $cart->estimatePriceTotal();

            return response()->json([
                'success' => true,
                'data' => [
                    'cart' => $cart,
                    'members' => $members,
                ]
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['cart', $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function previewProCart(Request $request) {
        try {
            $cart = Cart::findByUser($request->user()->id, null, true);
            if (!$cart) {
                return response()->json([
                    'success' => false,
                    'data' => null
                ]);
            }

            $cart->load(['items', 'items.package']);
            $cart->updateTotalDuration();
            $cart->estimatePriceTotal();

            $cart->grouped_items = $cart->groupItemsByDurationBestFit();

            return response()->json([
                'success' => true,
                'data' => $cart
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['pro-cart', 'preview', $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function syncMembers(Request $request)
    {
        $cart = Cart::findByUser($request->user()->id, null, true);
        if (!$cart) {
            return response()->json([
                'success' => false,
            ], 404);
        }

        try {
            $members = $request->has('members') ? $request->members : [];
            $cart->members()->sync($members);
            $cart->items()->delete();
            $cart->estimatePriceTotal();

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['cart', 'sync-member', $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trainings with available packages
     * The packages still have members who haven't selected it yet.
     */
    public function getTrainingsWithPackages(Request $request)
    {
        // $user = User::find($request->user()->id);
        $company = $request->user()->companiesUserIsManager()->first();
        $cart = Cart::findByUser($request->user()->id, null, true);

        $memberIds = $cart->members()->pluck('users.id')->toArray();
        $totalMembers = count($memberIds);

        // Get trainings with packages
        $trainings = Training::active()->with([
            'packages' => function ($query) use ($memberIds, $totalMembers) {
                $query->withCount([
                    'users as registered_members_count' => function ($q) use ($memberIds) {
                        if (!empty($memberIds)) {
                            $q->whereIn('users.id', $memberIds);
                        } else {
                            $q->whereRaw('1 = 0');
                        }
                    }
                ]);
            }
        ])
            ->get()
            ->map(function ($training) use ($totalMembers) {
                $training->packages = $training->packages->map(function ($package) use ($totalMembers) {
                    $package->disabled_flg = $totalMembers > 0
                        ? $package->registered_members_count >= $totalMembers
                        : false;
                    return $package;
                });
                return $training;
            });


        return response()->json([
            'success' => true,
            'data' => $trainings
        ]);
    }

    /**
     * Add array package ids to cart
     */
    public function addPackagesToProCart(Request $request)
    {
        if (!$request->has('packageIds')) {
            return response()->json([
                'success' => false,
            ], 404);
        }

        $cart = Cart::findByUser($request->user()->id, null, true);
        if (!$cart) {
            return response()->json([
                'success' => false,
            ], 404);
        }

        try {
            $packageIds = $request->input('packageIds');
            $cart->items()
                ->whereNotIn('package_id', $packageIds)
                ->delete();
            foreach ($packageIds as $pkgId) {
                $cart->items()->updateOrCreate(['package_id' => $pkgId]);
            }

            $cart->updateTotalDuration();

            return response()->json([
                'success' => true,
                'is_creatable' => $cart->contract_creatable,
                'cnt_groups' => sizeof($cart->groupItemsByDurationBestFit())
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['pro-cart', 'packages'], $request->user()->id);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
