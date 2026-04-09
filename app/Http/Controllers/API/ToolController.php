<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ToolController extends Controller
{
    public function getSelectedProductBrands(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->selected_product_brands
        ]);
    }

    public function updateSelectedProductBrands(Request $request)
    {
        if ($request->filled('brands')) {
            $user = $request->user();
            $user->selected_product_brands = $request->brands;
            $user->save();
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
