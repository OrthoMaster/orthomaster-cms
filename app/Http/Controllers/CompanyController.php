<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Odoo\OdooAccountController;
use App\Models\Company;
use App\Models\FundingOrganization;
use App\Models\Profession;
use App\Models\User;
use App\Services\OdooService;
use App\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TCG\Voyager\Models\Role;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::query();
        if ($request->filled('name')) {
            $query->where('name', 'LIKE', '%' . $request->name . '%');
        }
        if ($request->filled('first_name')) {
            $query->where('first_name', 'LIKE', '%' . $request->first_name . '%');
        }
        if ($request->filled('email')) {
            $query->where('email', 'LIKE', '%' . $request->email . '%');
        }

        if ($request->has('status')) {
            if ($request->status === '2') {
                $query->whereNotNull('invite_code');
            }

            // TODO: When has error in sync process
            if ($request->status === '3') {
                $query->where('status', '=', 'error');
            }
        }

        $companies = $query->latest('id')->paginate(20);
        $companies->appends($request->query());

        return view('companies.index', compact('companies'));
    }

    public function show(string $id)
    {
        $company = Company::findById($id);
        if (!$company) {
            return abort(404);
        }

        return view('companies.browse-accounts', compact('company'));
    }

    /**
     * Update manager
     */
    public function updateManager(string $id, string $userId)
    {
        $user = User::where('id', $userId)->first();

        if (!$user) {
            return abort(404);
        }

        $company = $user->companies()->where('company_id', $id)->first();
        if (!$company) {
            return back()->with([
                'message' => "La société n'existe pas.",
                'alert-type' => 'error'
            ]);
        }

        if ($company) {
            $user->companies()->updateExistingPivot($id, [
                'manager_flg' => !$company->pivot->manager_flg,
            ]);
        }

        return back()->with([
            'message' => "Mis à jour avec succès",
            'alert-type' => 'success'
        ]);
    }

    /**
     * Generate invite code to fill form
     */
    public function generateInviteCode(string $id)
    {
        $company = Company::findById($id);
        if (!$company) {
            return abort(404);
        }

        if ($company->invite_code) {
            return back()->with([
                'message' => "Le code d'invitation existe déjà",
                'alert-type' => 'error'
            ]);
        }

        try {
            do {
                $code = strtoupper(Str::random(8));
            } while (Company::where('invite_code', $code)->exists());

            $company->invite_code = $code;
            $company->created_by = Auth::user()->id;
            $company->save();

            return back()->with([
                'message' => 'Généré avec succès',
                'alert-type' => 'success'
            ]);
        } catch (\Exception $e) {
            return back()->with([
                'message' => $e->getMessage(),
                'alert-type' => 'error'
            ]);
        }
    }

    /**
     * Generate join code to send for other users want to join
     */
    public function generateJoinCode(string $id)
    {
        $company = Company::findById($id);
        if (!$company) {
            return false;
        }

        if ($company->join_code) {
            return true;
        }

        try {
            do {
                $code = Str::random(12);
            } while (Company::where('join_code', $code)->exists());

            $company->join_code = $code;
            $company->save();

            return true;
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['company', 'join-code', $id]);
            return false;
        }
    }

    /**
     * Re-open form
     */
    public function backEditable(string $id)
    {
        $company = Company::findById($id);
        if (!$company) {
            return abort(404);
        }
        $company->status = null;
        $company->save();

        return back()->with([
            'message' => 'Mis à jour avec succès',
            'alert-type' => 'success'
        ]);
    }

    /**
     * Fill data from form to DB
     */
    public function fillDataFromTemps(string $id)
    {
        $company = Company::findById($id);
        if (!$company) {
            return abort(404);
        }
        $this->generateJoinCode($id);

        DB::beginTransaction();
        try {
            $userRole = Role::where('name', 'user')->first();

            // Add members
            foreach ($company->users_temp as $temp) {
                $passwordTemp = Str::random(10);

                $professionId = null;
                $profession = Profession::find($temp['job']);
                if ($profession) $professionId = $profession->id;

                $organizationId = null;
                $organization = FundingOrganization::find($temp['organization']);
                if ($organization) $organizationId = $organization->id;

                // Create new user
                $user = User::firstOrCreate(
                    ['email' => $temp['email']],
                    [
                        'role_id' => $userRole->id,
                        'name' => mb_convert_case(trim($temp['name']), MB_CASE_TITLE, 'UTF-8'),
                        'first_name' => mb_convert_case(trim($temp['firstname']), MB_CASE_TITLE, 'UTF-8'),
                        'password_temp' => $passwordTemp,
                        'password' => bcrypt($passwordTemp),
                        
                        'establishment_id' => $company->establishment_id,
                        'profession_id' => $professionId,
                        
                        'no_prof_type_id' => $temp['rppsType'] !== 'other' ? $temp['rppsType'] : null,
                        'no_prof' => $temp['rpps'],
                        
                        'funding_organization_id' => $organizationId,
                        'no_secu' => trim($temp['secu']),
                        'first_name_birth' => mb_convert_case(trim($temp['firstnameBirth']), MB_CASE_TITLE, 'UTF-8'),
                        'date_of_birth' => $temp['dob'] ?: null,
                        
                        'status' => Status::ACTIVE->value,
                        'created_by' => Auth::id()
                    ]
                );

                // Add user to company
                $company->users()->attach($user->id, [
                    'manager_flg' => $temp['isLeader'] ?? false,
                ]);
            }

            $company->invite_code = null;
            $company->users_temp = null;
            $company->status = null;
            $company->save();

            DB::commit();

            // Send email to all members
            dispatch(function () use ($id) {
                (new \App\Http\Controllers\CompanyController())->sendAuthEmails($id);
            })->afterResponse();

            return $this->connectOdoo($id);
        } catch (\Exception $e) {
            DB::rollback();

            Log::error($e->getMessage(), ['company', 'fill-member', $id]);
            return back()->with([
                'message' => $e->getMessage(),
                'alert-type' => 'error'
            ]);
        }
    }

    /**
     * Connect and create info to Odoo
     */
    public function connectOdoo(string $id)
    {
        $company = Company::find($id);
        if (!$company) {
            return abort(404);
        }

        try {
            // Check before connect with Odoo
            $odooAccountController = new OdooAccountController(app(OdooService::class));
            if (!$company->id_odoo) {
                // Connect company with Odoo
                $storeOdooCompany = $odooAccountController->storeCompany($company);

                if (!$storeOdooCompany) {
                    Log::error('Can not create Company with Odoo', ['odoo', 'create-company', $company->id]);
                    return back()->with([
                        'message' => "[Société] La connexion à Odoo a échoué.",
                        'alert-type' => 'error'
                    ]);
                }
            }

            // Connect members to Odoo
            $storeOdooUserSuccessFlg = true;
            foreach ($company->users as $member) {
                if (!$member->id_odoo_partner) {
                    // Connect user with Odoo
                    $storedOdooUser = $odooAccountController->store($member);
                    if (!$storedOdooUser) {
                        Log::error('Can not create User with Odoo', ['odoo', 'create-member-of-company', $member->id]);
                        $storeOdooUserSuccessFlg = false;
                        continue;
                    }
                }

                // Add manager
                if ($member->pivot->manager_flg) {
                    $odooAccountController->addManagerToCompany($company->id_odoo, $member->id_odoo_partner);
                }

                // Add to member group
                $odooAccountController->addMemberToCommpany($company->id_odoo, $member->id_odoo_partner);
            }

            // Sync with old version DB
            if ($storeOdooUserSuccessFlg) {
                $storeOdooUserSuccessFlg = $odooAccountController->syncWithOldVersionForCompanyAndMembers($company->id);
            }

            if (!$storeOdooUserSuccessFlg) {
                Log::error('Can not sync Company and Members to Odoo', ['odoo', 'company', $company->id]);
                return back()->with([
                    'message' => "Une erreur s'est produite lors de la connexion à Odoo.",
                    'alert-type' => 'error'
                ]);
            }

            return back()->with([
                'message' => "Connexion à Odoo réussie.",
                'alert-type' => 'success'
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['odoo', 'company', $company->id]);
            return back()->with([
                'message' => $e->getMessage(),
                'alert-type' => 'error'
            ]);
        }
    }

    /**
     * Send auth emails to all members
     */
    public function sendAuthEmails(string $id)
    {
        $company = Company::find($id);
        if (!$company) {
            return abort(404);
        }
        try {
            foreach ($company->users as $user) {
                if ($user->password_temp) {
                    MailController::createdNewAccount($user);
                }
            }

            return back()->with([
                'message' => "Envoyé les emails avec succès.",
                'alert-type' => 'success'
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['company', 'send-auth-emails']);
            return back()->with([
                'message' => $e->getMessage(),
                'alert-type' => 'error'
            ]);
        }
    }


    public function integrityCheck(string $id)
    {
        $company = Company::find($id);
        if (!$company) {
            return abort(404);
        }

        try {
            // Join code
            $this->generateJoinCode($id);

            // ...

            return back()->with([
                'message' => "Vérification d'intégrité terminée",
                'alert-type' => 'success'
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['company', 'integrity-check']);
            return back()->with([
                'message' => $e->getMessage(),
                'alert-type' => 'error'
            ]);
        }
    }
}
