<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Package;
use App\Models\User;
use App\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    public function getDetails(Request $request)
    {
        try {
            $carts = Cart::getByUser($request->user()->id);
            $carts->load('items.package');

            foreach ($carts as $cart) {
                $cart->updateTotalDuration();
            }

            return response()->json([
                'success' => true,
                'data' => $carts
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

            // if ($user->student_flg) {
            //     return response()->json([
            //         'success' => false,
            //     ], 409);
            // }
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
            $cart = Cart::findByUser(Auth::user()->id, $request->cart_id);
            $cart->items()->delete();

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

    public function syncMembers(Request $request)
    {
        $cart = Cart::findByUser($request->user()->id);
        try {
            $members = $request->has('members') ? $request->members : [];
            $cart->members()->sync($members);
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

    public function submit(Request $request) {}
}
