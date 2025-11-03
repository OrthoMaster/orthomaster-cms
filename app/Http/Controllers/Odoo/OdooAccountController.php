<?php

namespace App\Http\Controllers\Odoo;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\OldVersionCompany;
use App\Models\OldVersionUser;
use App\Models\User;
use App\Services\OdooService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OdooAccountController extends Controller
{
    const USER = 4;
    const MANAGER = 9;
    const COMPANY = 22;
    const CREATED_BY_CMS = 19;
    const NOT_YET_LOGGED_IN = 20;
    const LOGGED_IN = 21;

    public function __construct(
        protected OdooService $odooService
    ) {}

    public function store(User $user)
    {
        // Check in valid company (connected Odoo)
        if ($user->company) {
            if (!$user->company->id_odoo) {
                return false;
            }
        }

        // Update DB from Odoo Info if exist
        $odooUserId = $this->odooService->searchRead(
            'res.users',
            [['login', '=', $user->email]],
            ['id', 'login', 'partner_id'],
            1
        );

        // Exist Odoo user
        if (!empty($odooUserId)) {
            Log::error('Odoo user exists: ' . $user->email, ['odoo']);

            // Store current oddo info
            if ($user->oldVersion) {
                $oldVersionUser = OldVersionUser::find($user->oldVersion->id);
                $oldVersionUser->id_odoo_partner = $odooUserId[0]['partner_id'];
                $oldVersionUser->id_odoo_user = $odooUserId[0]['id'];
                $oldVersionUser->save();
            } else {
                $user->oldVersion()->create([
                    'name' => $user->name,
                    'lastname' => $user->first_name,
                    'email' => $user->email,
                    'id_odoo_partner' => $odooUserId[0]['partner_id'],
                    'id_odoo_user' =>  $odooUserId[0]['id'],
                ]);
            }
        }

        // Update Odoo
        $tags = [$this::CREATED_BY_CMS, $this::USER, $this::NOT_YET_LOGGED_IN];
        if ($user->role->name == 'user-manager') {
            $tags = [$this::CREATED_BY_CMS, $this::MANAGER, $this::NOT_YET_LOGGED_IN];
        }
        try {
            // Create contact
            $contactData = [
                'type' => 'contact',
                'company_type' => 'person',
                'name' => $user->name . ' ' . $user->first_name,
                'email' => $user->email,
                'phone' => $user->phone,

                'zip' => $user->postal_code,
                'city' => $user->city,
                'country_code' => $user->country_code,
                'country_id' => $user->country_code == 'FR' ? 75 : null,

                'x_studio_tudiant' => $user->student_flg,

                'x_studio_type_dtablissement_1' => $user->establishment ? $user->establishment->id_odoo : null,
                'x_cms_establishment_name' => $user->other_establishment ? $user->other_establishment : ($user->establishment ? $user->establishment->name : null),

                'x_studio_fonction_professionnelle_1' => $user->profession ? $user->profession->id_odoo : null,
                'x_cms_profession_name' => $user->other_profession ? $user->other_profession : ($user->profession ?  $user->profession->name : null),

                'x_cms_no_prof_type_name' => $user->noProfType ? $user->noProfType->name : '',
                'x_studio_num_professionnel' => $user->no_prof,
                'x_studio_nom_de_jeune_fille' => $user->first_name_birth,
                'x_studio_date_de_naissance' => $user->date_of_birth,
                'x_studio_firstname' => $user->name,
                'x_studio_lastname' => $user->first_name,
                'x_studio_source_cration' => 'App',
                'category_id' => $tags
            ];
            $odooPartnerId = $this->odooService->create('res.partner', $contactData);
            if (!is_numeric($odooPartnerId)) {
                Log::error('Odoo contact create unsuccessfully: ' . $user->email, ['odoo']);
                return false;
            }

            // Create user

            // $userData = [
            //     'name' => $user->name . ' ' . $user->first_name,
            //     'email' => $user->email,
            //     'login' => $user->email,
            //     'password' => 'Password@123',
            //     'partner_id' => $odooPartnerId,
            // ];
            // $odooUserId = $this->odooService->create('res.users', $userData, [
            //     'no_reset_password' => true,
            //     'no_send_email' => true,
            //     'mail_create_nolog' => true
            // ]);
            // if (!is_numeric($odooUserId)) {
            //     Log::error('Odoo user create unsuccessfully: ' . $user->email, ['odoo']);
            //     return false;
            // }

            // Sync with old version
            $oldVersionUser = OldVersionUser::create([
                'name' => $user->name . ' ' . $user->first_name,
                'email' => $user->email,
                'is_student' => $user->student_flg,
                'id_odoo_partner' => $odooPartnerId,
                'id_odoo_user' => $odooUserId ? $odooUserId : 100
            ]);

            $user->id_user = $oldVersionUser ? $oldVersionUser->id : null;
            $user->save();

            // Add member to company (Odoo)
            if ($user->company) {
                if ($user->role->name == 'user-manager') {
                    $this->addManagerToCompany($user->company->id_odoo, $user->oldVersion->id_odoo_partner);
                } else {
                    $this->addMemberToCommpany($user->company->id_odoo, $user->oldVersion->id_odoo_partner);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['odoo']);
            return false;
        }
    }

    public function active(User $user)
    {
        $oldVersionUser = $user->oldVersion;
        if (!$oldVersionUser) {
            Log::error('Old version does not exist :' . $user->email, ['odoo', 'db']);
            return false;
        }

        $partnerId = $oldVersionUser->id_odoo_partner;

        $contact = $this->odooService->searchRead(
            'res.partner',
            [['id', '=', $partnerId]],
            ['category_id'],
            1
        );
        if (empty($contact)) {
            Log::error('Odoo contact does not exist: ' . $partnerId, ['odoo']);
            return false;
        }

        $currentCategoryIds = $contact[0]['category_id'] ?? [];

        $newCategoryIds = array_values(array_filter($currentCategoryIds, function ($id) {
            return $id != $this::NOT_YET_LOGGED_IN;
        }));

        if (!in_array($this::LOGGED_IN, $newCategoryIds)) {
            $newCategoryIds[] = $this::LOGGED_IN;
        }

        // Update
        $result = $this->odooService->write(
            'res.partner',
            [$partnerId],
            ['category_id' => [[6, 0, $newCategoryIds]]]
        );

        return $result;
    }

    public function getContactInfo($idOdooUser)
    {
        $contact = $this->odooService->searchRead(
            'res.partner',
            [['id', '=', $idOdooUser]],
            [
                'id',
                'name',
                'x_studio_type_dtablissement_1',
                'x_studio_fonction_professionnelle_1',
                'x_studio_membre_socit',
                'x_studio_utilisateurs_responsables'
            ],
            1
        );

        return empty($contact) ? null : $contact[0];
    }

    /**
     * Store new company
     */
    public function storeCompany(Company $company)
    {
        if ($company->id_odoo) {
            return true;
        }

        $companyData = [
            'type' => 'contact',
            'company_type' => 'company',
            'name' => $company->name,
            'phone' => $company->phone,
            'street' => $company->address,
            'zip' => $company->postal_code,
            'city' => $company->city,
            'country_code' => $company->country_code,
            'country_id' => ($company->country_code == 'FR' || $company->country == 'France') ? 75 : null,

            'x_studio_type_dtablissement_1' => $company->establishment ? $company->establishment->id_odoo : null,
            'x_cms_establishment_name' => $company->other_establishment ? $company->other_establishment : ($company->establishment ? $company->establishment->name : null),

            'vat' => $company->tva,
            'x_studio_n_siret' => $company->siret,
            'x_studio_source_cration' => 'App',
            'category_id' => [$this::CREATED_BY_CMS, $this::COMPANY]
        ];

        // Create odoo contact
        $companyId = $this->odooService->create('res.partner', $companyData);
        if (!is_numeric($companyId)) {
            Log::error('Odoo company create unsuccessfully: ' . $company->id, ['odoo']);
            return false;
        }

        $company->id_odoo = $companyId;
        $company->save();
        return true;
    }

    /**
     * Add member to company by Odoo IDs
     */
    public function addMemberToCommpany($companyId, $memberId)
    {
        // Check member is already in company
        $company = $this->odooService->searchRead(
            'res.partner',
            [['id', '=', $companyId], ['x_studio_membre_socit', 'in', [$memberId]]],
            ['id', 'x_studio_membre_socit'],
            1
        );
        if (!empty($company)) {
            Log::error('Odoo company has this user: ' . $companyId . '-' . $memberId, ['odoo']);
            return false;
        }

        // Add user to company
        $result = $this->odooService->write(
            'res.partner',
            [$companyId],
            ['x_studio_membre_socit' => [[4, $memberId, 0]]]
        );

        return $result;
    }

    /**
     * Add manager to company by Odoo IDs
     */
    public function addManagerToCompany($companyId, $managerId)
    {
        // Check manager is in company
        $company = $this->odooService->searchRead(
            'res.partner',
            [['id', '=', $companyId], ['x_studio_utilisateurs_responsables', 'in', [$managerId]]],
            ['id', 'x_studio_utilisateurs_responsables'],
            1
        );
        if (!empty($company)) {
            Log::error('Odoo company has this user: ' . $companyId . '-' . $managerId, ['odoo']);
            return false;
        }

        // Add manager to company
        $result = $this->odooService->write(
            'res.partner',
            [$companyId],
            ['x_studio_utilisateurs_responsables' => [[4, $managerId, 0]]]
        );

        return $result;
    }

    /**
     * Sync with old version from Company and members to Odoo
     */
    public function syncWithOldVersionForCompanyAndMembers(Company $company)
    {
        try {
            // Sync with old version        
            $oldVersionCompany = OldVersionCompany::create([
                'name' => $company->name,
                'id_odoo_partner' => $company->id_odoo
            ]);

            $company->id_office = $oldVersionCompany->id;
            $company->save();

            foreach ($company->users as $member) {
                DB::table('user_belong_to_office')->insert([
                    'id_office' => $oldVersionCompany->id,
                    'id_user' => $member->id_user,
                    'is_office_manager' => $member->role->name == 'user-manager'
                ]);
                DB::table('user_manage_office')->insert([
                    'id_office' => $oldVersionCompany->id,
                    'id_user' => $member->id_user,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['sync-company', 'odoo']);
            return false;
        }
    }


    public function checkExist()
    {
        $partnerId = 1;
        $checkId = 1;
        $partners = $this->odooService->searchRead(
            'res.partner',
            [['id', '=', $partnerId], ['category_id', 'in', [$checkId]]],
            ['id', 'name']
        );

        if (!empty($partners)) {
            // Exist checkId
        } else {
            // None
        }
    }

    public function test()
    {
        $oldVersionCompany = OldVersionCompany::create([
            'name' => 'asdfasdf',
            'id_odoo_partner' => 1223
        ]);

        dd($oldVersionCompany);
    }
}
