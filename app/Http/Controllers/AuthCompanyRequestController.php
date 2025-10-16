<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\FundingOrganization;
use App\Models\NoProfType;
use App\Models\Profession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthCompanyRequestController extends Controller
{
    public function view($code)
    {
        $company = Company::findByInviteCode($code);
        if (!$company) {
            return abort(404);
        };

        $professions = Profession::getAll();
        $noProfTypes = NoProfType::getAll();
        $organizations = FundingOrganization::getAll();

        // Countries
        $path = public_path('countries.json');
        $json = file_get_contents($path);
        $countries = json_decode($json, true);

        return view(
            'landingpage.register-company-request',
            compact('company', 'professions', 'noProfTypes', 'organizations', 'countries')
        );
    }

    public function saveAsDraft(Request $request)
    {
        try {
            $company = Company::findByInviteCode($request->invite_code);
            if (!$company) {
                return response()->json([
                    'status' => 'failed',
                    'errors' => ['La session a expiré, veuillez réessayer']
                ], 404);
            };

            $company->name = $request->company_name;
            $company->siret = $request->siret;
            $company->phone = $request->phone;
            $company->address = $request->address;
            $company->postal_code = $request->postal_code;
            $company->city = $request->city;
            $company->country = $request->country;

            $company->users_temp = json_decode($request->members, true);

            $company->save();

            // Return success
            return response()->json([
                'status' => 'success',
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['company-register', 'save-as-draft']);

            return response()->json([
                'status' => 'failed',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }
}
