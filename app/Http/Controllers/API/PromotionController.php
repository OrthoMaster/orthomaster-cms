<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\User;

class PromotionController extends Controller
{
    public function validate(Request $request)
    {
        $code = strtoupper($request->input('code'));
        $orderAmount = $request->input('order_amount');
        
        $promotion = Promotion::where('code', $code)->first();

        if (!$promotion) {
            return response()->json([
                'success' => false,
                'message' => 'Le code promotionnel est invalide ou a expiré.',
            ], 404);
        }

        $user = User::find($request->user()->id);
        $validation = $promotion->canBeUsedBy($user, $orderAmount);
        

        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $validation['message'],
            ], 422);
        }

        $tva = (float) setting('system.tva');
        $discountAmount = round($promotion->calculateDiscount($orderAmount), 2);
        $finalAmountHT = round($orderAmount - $discountAmount, 2);
        $tvaAmount = round($finalAmountHT * $tva / 100, 2);
        $finalAmount = round($finalAmountHT + $tvaAmount, 2);

        return response()->json([
            'success' => true,
            'message' => 'Code promotionnel valide',
            'data' => [
                'promotion' => [
                    'id' => $promotion->id,
                    'code' => $promotion->code,
                    'name' => $promotion->name,
                    'description' => $promotion->description,
                    'discount_type' => $promotion->discount_type,
                    'discount_value' => $promotion->discount_value,
                ],
                'final_amount_ht' => $finalAmountHT,
                'discount_amount' => $discountAmount,
                'tva_amount' => $tvaAmount,
                'final_amount' => $finalAmount,
            ],
        ]);
    }
}
