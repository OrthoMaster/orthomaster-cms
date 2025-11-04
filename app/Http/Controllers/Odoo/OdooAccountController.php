<?php

namespace App\Http\Controllers\Odoo;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\OldVersionCompany;
use App\Models\OldVersionUser;
use App\Models\User;
use App\Services\OdooService;
use App\Status;
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
        try {
            // Define tags
            $tags = [$this::CREATED_BY_CMS, $this::USER, $this::NOT_YET_LOGGED_IN];
            if ($user->role && $user->role->name == 'user-manager') {
                $tags = [$this::CREATED_BY_CMS, $this::MANAGER, $this::NOT_YET_LOGGED_IN];
            }

            // Active user
            $user->status = Status::ACTIVE->value;

            // Get info from Odoo Info if exist
            $exOdooUserId = $this->odooService->searchRead(
                'res.users',
                [['login', '=', $user->email]],
                ['id', 'login', 'partner_id'],
                1
            );
            // Store Odoo info into DB
            if (!empty($exOdooUserId)) {
                Log::error('Odoo user exists: ' . $user->email, ['odoo']);

                $user->id_odoo_partner = $exOdooUserId[0]['partner_id'];
                $user->id_odoo_user = $exOdooUserId[0]['id'];
                $user->save();

                User::syncWithOldVersionDB($user->id);
                return true;
            }

            $odooPartnerId = $user->id_odoo_partner;
            $odooUserId = $user->id_odoo_user;

            // Create Odoo contact
            if (!$odooPartnerId) {
                $odooUserId = null; // Reset Odoo user

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

                    'x_studio_n_securite_sociale' => $user->no_secu,

                    'x_cms_funding_organization' => $user->organization ? $user->organization->name : null,

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

                $user->id_odoo_partner = $odooPartnerId;
                $user->save();
            }

            // Create Odoo user
            // if (!$odooUserId) {
            //     $userData = [
            //         'name' => $user->name . ' ' . $user->first_name,
            //         'email' => $user->email,
            //         'login' => $user->email,
            //         'password' => 'Password@123',
            //         'partner_id' => $odooPartnerId,
            //     ];
            //     $odooUserId = $this->odooService->create('res.users', $userData, [
            //         'no_reset_password' => true,
            //         'no_send_email' => true,
            //         'mail_create_nolog' => true
            //     ]);
            //     if (!is_numeric($odooUserId)) {
            //         Log::error('Odoo user create unsuccessfully: ' . $user->email, ['odoo']);
            //         return false;
            //     }

            //     $user->id_odoo_user = $odooUserId;
            //     $user->save();
            // }

            User::syncWithOldVersionDB($user->id);
            return true;
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['odoo', 'create-partner-user']);
            return false;
        }
    }

    public function active(User $user)
    {
        if (!$user->id_odoo_partner) {
            return false;
        }

        $contact = $this->odooService->searchRead(
            'res.partner',
            [['id', '=', $user->id_odoo_partner]],
            ['category_id'],
            1
        );
        if (empty($contact)) {
            Log::error('Odoo contact does not exist: ' . $user->id_odoo_partner, ['odoo']);
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
            [$user->id_odoo_partner],
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
    public function addMemberToCommpany($companyId, $memberId, $hasCheckCompany = false)
    {
        // Check member is already in company
        if ($hasCheckCompany) {
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
    public function addManagerToCompany($companyId, $managerId, $hasCheckCompany = false)
    {
        // Check manager is in company
        if ($hasCheckCompany) {
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
    public function syncWithOldVersionForCompanyAndMembers($companyId)
    {
        try {
            $company = Company::find($companyId);

            // Sync with old version        
            $oldVersionCompany = OldVersionCompany::create([
                'name' => $company->name,
                'id_odoo_partner' => $company->id_odoo
            ]);

            $company->id_office = $oldVersionCompany->id;
            $company->save();

            foreach ($company->users as $member) {
                if ($member->id_user) {
                    Log::warning('Missing sync belong office by user: '.$member->id, ['sync-company', 'odoo']);
                    continue;
                }

                DB::table('user_belong_to_office')->insert([
                    'id_office' => $oldVersionCompany->id,
                    'id_user' => $member->id_user,
                    'is_office_manager' => ($member->role & $member->role->name == 'user-manager') ? true : false
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
}
