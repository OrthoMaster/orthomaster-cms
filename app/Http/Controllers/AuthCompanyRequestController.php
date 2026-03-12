<?php

namespace App\Http\Controllers;

use App\Mail\CreatedCompanyByUserMail;
use App\Models\Company;
use App\Models\Establishment;
use App\Models\FundingOrganization;
use App\Models\NoProfType;
use App\Models\Profession;
use App\Models\User;
use App\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthCompanyRequestController extends Controller
{
    public function storeByUser(Request $request)
    {
        if (!$request->has('email')) {
            return abort(404);
        }

        $user = User::findByEmail($request->email);
        // Can not create company when is manager
        if ($user->companiesUserIsManager->isNotEmpty()) {
            return abort(404);
        }

        try {
            do {
                $inviteCode = strtoupper(Str::random(8));
            } while (Company::where('invite_code', $inviteCode)->exists());
            do {
                $joinCode = Str::random(12);
            } while (Company::where('join_code', $joinCode)->exists());

            // Create new company
            $company = Company::create([
                'name' => 'Votre établissement',
                'invite_code' => $inviteCode,
                'join_code' => $joinCode,
                'active_flg' => true,
                'created_by' => $user->id,
            ]);
            // Add user to company
            $company->users()->attach($user->id, [
                'manager_flg' => true,
            ]);

            // Send email
            Mail::to($user->email)->send(new CreatedCompanyByUserMail($user, $company));
            Log::channel('mail')->info('After created company: ' . $user->email, ['create-company', $company->id]);

            return redirect()->route('register.company.view', $inviteCode);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['create-company-by-user', $request->except('token')]);
            return abort(500);
        }
    }

    public function view($code)
    {
        $company = Company::findByInviteCode($code);
        if (!$company) {
            return view('landingpage.template.error-group-code', ['statusCode' => 404]);
        };

        $estabs = Establishment::getAll();
        $professions = Profession::getAll();
        $noProfTypes = NoProfType::getAll();
        $organizations = FundingOrganization::getAll();

        // Countries
        $path = public_path('countries.json');
        $json = file_get_contents($path);
        $countries = json_decode($json, true);

        return view(
            'landingpage.register-company-request',
            compact('company', 'estabs', 'professions', 'noProfTypes', 'organizations', 'countries')
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
            $company->establishment_id = $request->company_estab;

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

    public function redirectRegisterView(Request $request)
    {
        if (!$request->filled('x_id') || !$request->filled('y_id')) {
            return abort(404);
        }

        $userId = $request->query('x_id');
        $companyId = $request->query('y_id');
        $company = Company::find($companyId);
        $user = User::find($userId);
        $userInCompany = $company->users()->where('email', $user->email)->first();
        if (!$userInCompany) {
            return abort(404);
        }

        try {
            if (!$company->invite_code) {
                do {
                    $code = strtoupper(Str::random(8));
                } while (Company::where('invite_code', $code)->exists());

                $company->invite_code = $code;
                $company->save();
            }

            return redirect()
                ->route('register.company.view', $company->invite_code)
                ->withQuery(['inapp' => 'true']);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['redirect-add-mebers-from-app']);
            return abort(500);
        }
    }
}
