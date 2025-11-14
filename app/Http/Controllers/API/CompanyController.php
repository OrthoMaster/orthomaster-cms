<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    public function getMembers()
    {
        try {
            $user = User::find(Auth::user()->id);
            $members = null;

            $company = $user->companiesUserIsManager()->first();
            if ($company) {
                $members = $company->users()
                    ->wherePivot('manager_flg', false)
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $members
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['get-company-members']);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
