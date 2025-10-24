<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    public function show()
    {
        try {
            $carts = Cart::getByUser(Auth::user()->id);

            return response()->json([
                'success' => true,
                'data' => $carts
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['show-carts']);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addPackage($id, Request $request)
    {
        try {
            $package = Package::findById($id);
            if (!$package) {
                return response()->json([
                    'success' => false,
                ], 404);
            }

            // Check addable - not in selected packages
            $user = User::find(Auth::user()->id);
            if (!$user->student_flg) {
                $packageIds = $user->packages->pluck('packages.id')->toArray();
                if (in_array($package->id, $packageIds)) {
                    return response()->json([
                        'success' => false,
                    ], 409);
                }
            }

            // Add item to cart
            $cart = Cart::findByUser(Auth::user()->id, $request->cart_id);
            $cart->items()->updateOrCreate(['package_id' => 1]);

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['cart', 'add-package']);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function removePackage($id, Request $request)
    {
        try {
            $cart = Cart::findByUser(Auth::user()->id, $request->cart_id);
            $cart->items()->where('package_id', $id)->delete();

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['cart', 'remove-package']);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function clear(Request $request) {
        try {
            $cart = Cart::findByUser(Auth::user()->id, $request->cart_id);
            $cart->items()->delete();

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['cart', 'clear-package']);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function syncMembers(Request $request) {
        $cart = Cart::findByUser(Auth::user()->id, $request->cart_id);
        
        $members = $request->has('members') ? $request->members : [];
        foreach ($members as $member) {
            // Check member

            // Add into cart
        }

        return response()->json([
            'success' => false,
        ]);
    }
}
