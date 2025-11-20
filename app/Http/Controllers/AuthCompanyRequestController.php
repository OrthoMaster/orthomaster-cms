<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\FundingOrganization;
use App\Models\NoProfType;
use App\Models\Profession;
use App\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthCompanyRequestController extends Controller
{
    public function view($code)
    {
        $company = Company::findByInviteCode($code);
        if (!$company) {
            return view('landingpage.template.error-group-code', ['statusCode' => 404]);
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

    public function submit(Request $request)
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
            $company->email = $request->company_email;

            $company->siret = $request->siret;
            $company->phone = $request->phone;
            $company->address = $request->address;
            $company->postal_code = $request->postal_code;
            $company->city = $request->city;
            $company->country = $request->country;

            $company->users_temp = json_decode($request->members, true);

            // Submit
            if ($request->has('isSubmitted')) {
                $company->status = Status::PENDING->value;
            }
            $company->save();

            // Submit - Fill data into database
            if ($request->has('isSubmitted')) {
                dispatch(function () use ($company) {
                    (new \App\Http\Controllers\CompanyController())->fillDataFromTemps($company->id);
                })->afterResponse();
            }
            
            // Return success
            return response()->json([
                'status' => 'success',
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['company-register', 'submit']);

            return response()->json([
                'status' => 'failed',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }
}
