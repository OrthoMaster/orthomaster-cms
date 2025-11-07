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
        $user = User::where('id', $userId)->where('company_id', $id)->first();

        if (!$user) {
            return abort(404);
        }

        $managerRole = Role::where('name', 'user-manager')->first();
        if ($managerRole && $user->role_id !== $managerRole->id) {
            $user->role_id = $managerRole->id;
            $user->save();

            return back()->with([
                'message' => 'Mise à jour réussie',
                'alert-type' => 'success'
            ]);
        }

        if ($user->role_id == $managerRole->id) {
            $userRole = Role::where('name', 'user')->first();
            $user->role_id = $userRole->id;
            $user->save();

            return back()->with([
                'message' => 'Mise à jour réussie',
                'alert-type' => 'success'
            ]);
        }

        return back()->with([
            'message' => "Il n'y a rien à mettre à jour",
            'alert-type' => 'warning'
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

        DB::beginTransaction();
        try {
            $userRole = Role::where('name', 'user')->first();
            $managerRole = Role::where('name', 'user-manager')->first();

            foreach ($company->users_temp as $temp) {
                $passwordTemp = Str::random(10);

                $professionId = null;
                $profession = Profession::find($temp['job']);
                if ($profession) $professionId = $profession->id;

                $organizationId = null;
                $organization = FundingOrganization::find($temp['organization']);
                if ($organization) $organizationId = $organization->id;

                // Create new user
                $user = User::create([
                    'role_id' => $temp['isLeader'] ? $managerRole->id : $userRole->id,
                    'name' => mb_convert_case(trim($temp['name']), MB_CASE_TITLE, 'UTF-8'),
                    'first_name' => mb_convert_case(trim($temp['firstname']), MB_CASE_TITLE, 'UTF-8'),
                    'email' => $temp['email'],
                    'password_temp' => $passwordTemp,
                    'password' => bcrypt($passwordTemp),

                    // 'student_flg' => $temp['isStudent'],

                    'establishment_id' => $company->establishment_id,
                    'profession_id' => $professionId,

                    'no_prof_type_id' => $temp['rppsType'] !== 'other' ? $temp['rppsType'] : null,
                    'no_prof' => $temp['rpps'],

                    'company_id' => $company->id,
                    'funding_organization_id' => $organizationId,
                    'no_secu' => trim($temp['secu']),
                    'first_name_birth' => mb_convert_case(trim($temp['firstnameBirth']), MB_CASE_TITLE, 'UTF-8'),
                    'date_of_birth' => $temp['dob'] ? $temp['dob'] : null,

                    'status' => Status::PENDING->value,
                    'created_by' => Auth::user()->id
                ]);
            }

            $company->invite_code = null;
            // $company->users_temp = null;
            $company->status = null;
            $company->save();

            DB::commit();

            return back()->with([
                'message' => 'Généré avec succès',
                'alert-type' => 'success'
            ]);
        } catch (\Exception $e) {
            DB::rollback();

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
                        $storeOdooUserSuccessFlg = false;
                        continue;
                    }
                }

                // Add member to company (Odoo)
                if ($member->role && $member->role->name == 'user-manager') {
                    $odooAccountController->addManagerToCompany($company->id_odoo, $member->id_odoo_partner);
                } else {
                    $odooAccountController->addMemberToCommpany($company->id_odoo, $member->id_odoo_partner);
                }
            }

            // Sync with old version DB
            if ($storeOdooUserSuccessFlg) {
                $storeOdooUserSuccessFlg = $odooAccountController->syncWithOldVersionForCompanyAndMembers($company->id);
            }

            if (!$storeOdooUserSuccessFlg) {
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
            Log::error($e->getMessage(), ['company', 'connect-odoo', 'odoo']);
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
                // MailController::createdNewAccount($user);
            }

            return back()->with([
                'message' => "Connexion à Odoo réussie.",
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
}
