<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Establishment;
use App\Models\FundingOrganization;
use App\Models\OldVersionUser;
use App\Models\Profession;
use App\Models\SystemNotification;
use App\Models\Topic;
use App\Models\User;
use App\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use TCG\Voyager\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)
            ->with('role')
            ->first();

        // Copy if email exists old version
        if (!$user) {
            $this->copyOldAccount($request->email);
        }

        // Check
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Ces informations d\'identification ne correspondent pas à nos enregistrements.'
            ], 422);
        }

        // Check expire
        if ($user->student_flg && $user->expires_at < now()) {
            return response()->json([
                'error' => "Votre compte est actuellement expiré. Merci de nous contacter et de transmettre un nouveau certificat de scolarité pour le débloquer."
            ], 422);
        }

        // Delete if has access token
        $deviceToken = DeviceToken::where('device_id', $request->device_id)
            ->where('user_id', $user->id)
            ->first();
        if ($deviceToken && $deviceToken->accessToken) {
            $user->tokens()->where('name', $deviceToken->device_id)->delete();
        }

        // Create new access token
        $deviceName = $request->device_id ? $request->device_id : 'mobile';
        $token = $user->createToken($deviceName);
        $accessTokenId = $token->accessToken->id;

        // Save device token
        if ($request->fcm_token) {
            $deviceToken = DeviceToken::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'device_id' => $request->device_id,
                ],
                [
                    'access_token_id' => $accessTokenId,
                    'fcm_token' => $request->fcm_token,
                    'device_type' => $request->device_type,
                    'last_used_at' => now(),
                ]
            );
        }

        // Sync with old system
        if ($user->password_temp != null) {
            $userId = $user->id;
            dispatch(function () use ($userId) {
                $user = \App\Models\User::find($userId);
                (new \App\Http\Controllers\Odoo\OdooAccountController(app(\App\Services\OdooService::class)))
                    ->active($user);
            })->afterResponse();

            // Clear password temp
            $user->password_temp = null;
            $user->save();
        }

        // Tracking app open
        $agent = $request->device_type;
        dispatch(function () use ($accessTokenId, $agent) {
            app(\App\Http\Controllers\Tracking\TrkAppOpenController::class)
                ->tracking($accessTokenId, $agent);
        })->afterResponse();

        return response()->json([
            'status' => 'success',
            'data'   => $this->customizeUserArray($user, $token->plainTextToken),
        ]);
    }

    public function sync(Request $request)
    {
        if (!$request->has('id_user') || !$request->has('password')) {
            Log::channel('api')->warning('Sync error with id_user: ' . $request->id_user, ['sync', 'auth']);
            return response()->json([], 400);
        }

        try {
            $oldVersionUser = OldVersionUser::where('id_user', $request->id_user)->first();
            if (!$oldVersionUser) {
                Log::channel('api')->warning('Sync error - not found with id_user: ' . $request->id_user, ['sync', 'auth']);
                return response()->json([], 400);
            }
            // Check user exist in new system - just sync password
            $user = User::where('email', $oldVersionUser->email)->first();
            if ($user) {
                $user->password = Hash::make($request->password);
                $user->password_temp = null;
                $user->id_user = $oldVersionUser->id_user;
                $user->id_odoo_partner = $oldVersionUser->id_odoo_partner;
                $user->id_odoo_user = $oldVersionUser->id_odoo_user;

                $user->save();

                return response()->json([], 200);
            }

            $role = Role::where('name', 'user')->first();
            $user = User::create([
                'role_id' => $role->id,
                'name' => $oldVersionUser->name,
                'email' => $oldVersionUser->email,
                'password' => Hash::make($request->password),

                'student_flg' => $oldVersionUser->is_student,
                'status' => Status::ACTIVE->value,
                'created_from' => 'CLONE', // Clone auto from old version by action login

                // Sync with old version
                'id_user' => $oldVersionUser->id_user,
                'id_odoo_partner' => $oldVersionUser->id_odoo_partner,
                'id_odoo_user' => $oldVersionUser->id_odoo_user,

                'expires_at' => $oldVersionUser->is_student ? ($oldVersionUser->created_at ? Carbon::parse($oldVersionUser->created_at)->addMonths(12)->startOfDay() : now()->addMonths(12)->startOfDay()) : null,
            ]);

            // Sync company
            User::syncOldVersionCompany($user->id, $oldVersionUser->id_user);

            // Sync trainings
            User::syncOldVersionTraining($user->id, $oldVersionUser->id_user);

            // Sync from Odoo
            $idOdooPartner = $oldVersionUser->id_odoo_partner;
            $userId = $user->id;
            dispatch(function () use ($idOdooPartner, $userId) {
                (new \App\Http\Controllers\Odoo\OdooAccountController(app(\App\Services\OdooService::class)))
                    ->syncFromOdooToNewUser($idOdooPartner, $userId);
            })->afterResponse();

            // Tracking app open
            $token = $request->token;
            $agent = $request->agent;
            dispatch(function () use ($token, $agent) {
                (new \App\Http\Controllers\Tracking\TrkAppOpenController())
                    ->tracking($token, $agent);
            })->afterResponse();

            return response()->json([], 200);
        } catch (\Exception $e) {
            Log::channel('api')->warning($e->getMessage(), ['sync', 'auth']);
            return response()->json([], 400);
        }
    }

    /**
     * Sync when use old account to log in into version 2.4+
     */
    public function copyOldAccount($email)
    {
        $oldVersionUser = OldVersionUser::where('email', $email)->first();
        if (!$oldVersionUser) {
            return false;
        }

        try {
            $role = Role::where('name', 'user')->first();
            $user = User::create([
                'role_id' => $role->id,
                'name' => $oldVersionUser->name,
                'email' => $oldVersionUser->email,
                'password' => Hash::make(Str::random(8)),

                'student_flg' => $oldVersionUser->is_student,
                'status' => Status::ACTIVE->value,
                'created_from' => 'CLONE_2.4+', // Clone auto from old version by action login

                // Sync with old version
                'id_user' => $oldVersionUser->id_user,
                'id_odoo_partner' => $oldVersionUser->id_odoo_partner,
                'id_odoo_user' => $oldVersionUser->id_odoo_user,

                'expires_at' => $oldVersionUser->is_student ? ($oldVersionUser->created_at ? Carbon::parse($oldVersionUser->created_at)->addMonths(12)->startOfDay() : now()->addMonths(12)->startOfDay()) : null,
            ]);

            // Sync company
            User::syncOldVersionCompany($user->id, $oldVersionUser->id_user);

            // Sync trainings
            User::syncOldVersionTraining($user->id, $oldVersionUser->id_user);

            return true;
        } catch (\Exception $e) {
            Log::channel('api')->warning($e->getMessage(), ['sync', 'login', '2.4+']);
            return false;
        }
    }

    /**
     * Get info of current auth user
     */
    public function me(Request $request)
    {
        $user = Auth::user();
        $token = $request->user()->currentAccessToken()->token;

        // Tracking
        $agent = $this->detectClientType($request);
        $accessTokenId = $request->bearerToken();
        dispatch(function () use ($accessTokenId, $agent) {
            app(\App\Http\Controllers\Tracking\TrkAppOpenController::class)
                ->tracking($accessTokenId, $agent);
        })->afterResponse();

        return response()->json([
            'status' => 'success',
            'data'   => $this->customizeUserArray($user, $token),
        ]);
    }

    /**
     * detect client type from request
     */
    private function detectClientType(Request $request)
    {
        $userAgent = $request->userAgent() ?? '';

        if (preg_match('/Android/i', $userAgent)) return 'android';
        if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) return 'ios';

        return 'web';
    }

    /**
     * Get full info for Profile screen
     */
    public function more(Request $request)
    {
        $companiesIsMember = $request->user()->companiesUserIsMember()
            ->with('establishment:id,name')
            ->get();
        $companyIsManager = $request->user()->companiesUserIsManager()
            ->with('establishment:id,name')
            ->first();
        $hasNotifications = sizeof(SystemNotification::getByReceiver($request->user()->id)) > 0;

        $estabs = Establishment::getAll();

        return response()->json([
            'success' => true,
            'data' => [
                'companiesIsMember' => $companiesIsMember,
                'companyIsManager' => $companyIsManager,
                'hasNotifications' => $hasNotifications,
                'estabs' => $estabs
            ]
        ]);
    }

    /**
     * Change password for the first time
     */
    public function changeInitPassword(Request $request)
    {
        $request->validate([
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::find(Auth::user()->id);
        $user->password = Hash::make($request->new_password);

        // Clear password temp
        if ($user->password_temp !== null) {
            $user->password_temp = null;
        }

        $user->save();

        // Sync with old system 
        // $idOdooUser = $user->id_odoo_user;
        // $newPassword = $request->new_password;
        // dispatch(function () use ($idOdooUser, $newPassword) {
        //     (new \App\Http\Controllers\Odoo\OdooAccountController(app(\App\Services\OdooService::class)))
        //         ->syncPassword($idOdooUser, $newPassword);
        // })->afterResponse();

        return response()->json([
            'status' => 'success',
            'message' => 'Le mot de passe a été modifié avec succès.',
        ]);
    }

    /**
     * Get info to view Profile Info screen
     */
    public function getPreFromData()
    {
        try {
            $estabs = Establishment::getAll();
            $professions = Profession::getAll();
            $organizations = FundingOrganization::getAll();
            $topics = Topic::getAll();

            return response()->json([
                'success' => true,
                'data' => [
                    'estabs' => $estabs,
                    'professions' => $professions,
                    'organizations' => $organizations,
                    'topics' => $topics,
                ]
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['pre-form-data', 'profile']);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request)
    {

        try {
            $validator = $request->validate([
                'name' => 'required|string|max:100',
                'first_name' => 'nullable|string|max:100',
                'phone' => 'nullable|regex:/^\+?[0-9]{1,3}[\s\-]?(\(?\d{1,4}\)?[\s\-]?){1,}[0-9]{1,}$/|min:10|max:20',
                'date_of_birth' => 'nullable|date_format:d/m/Y',
                'first_name_birth' => 'nullable|string|max:100',

                'country' => 'nullable|max:100',
                'postal_code' => 'nullable|max:10',
                'city' => 'nullable|max:30',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
            ], 422);
        }

        $dateOfBirth = null;
        if ($request->filled('date_of_birth')) {
            try {
                $dateOfBirth = \Carbon\Carbon::createFromFormat('d/m/Y', $request->date_of_birth)->format('Y-m-d');
            } catch (\Exception $e) {
                $dateOfBirth = null;
            }
        }

        try {
            $user = $request->user();
            $user->update([
                'name' => mb_convert_case(trim($request->name), MB_CASE_TITLE, 'UTF-8'),
                'first_name' => mb_convert_case(trim($request->first_name), MB_CASE_TITLE, 'UTF-8'),
                'first_name_birth' => mb_convert_case(trim($request->first_name_birth), MB_CASE_TITLE, 'UTF-8'),
                'date_of_birth' => $dateOfBirth,

                'phone' => $request->phone,
                'country' => $request->country,
                'postal_code' => trim($request->postal_code),
                'city' => mb_convert_case(trim($request->city), MB_CASE_TITLE, 'UTF-8'),

                'profession_id' => $request->profession_id,
                'funding_organization_id' => $request->funding_organization_id,

                'no_secu' => $request->no_secu,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Profil mis à jour avec succès"
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['update-profile', $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update settings
     */
    public function updateNotiPermission(Request $request)
    {
        try {
            $user = $request->user();
            $user->update([
                'notification_flg' => $request->notifications,
            ]);

            return response()->json([
                'success' => true,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['update-noti-permission', $request->user()->id]);
            return response()->json([
                'success' => false,
            ], 500);
        }
    }

    /**
     * Update settings
     */
    public function updateSettings(Request $request)
    {
        try {
            $user = $request->user();
            $user->update([
                'notification_flg' => $request->notifications,
            ]);
            $topics = $request->has('topicIds') ? $request->topicIds : [];
            $user->topics()->sync($topics);

            return response()->json([
                'success' => true,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['update-settings', $request->user()->id]);
            return response()->json([
                'success' => false,
            ], 500);
        }
    }

    /**
     * Add topic
     */
    public function addTopic(Request $request)
    {
        if (!$request->has('topicId')) {
            return response()->json([
                'success' => false,
            ]);
        }

        try {
            $user = $request->user();
            $user->topics()->attach($request->topicId);

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['add-topic', $request->user()->id]);
            return response()->json([
                'success' => false,
            ], 500);
        }
    }

    /**
     * Update password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $user = User::find(Auth::user()->id);
            $user->password = Hash::make($request->new_password);
            $user->save();

            // Sync with old system
            // $idOdooUser = $user->id_odoo_user;
            // $newPassword = $request->new_password;
            // dispatch(function () use ($idOdooUser, $newPassword) {
            //     (new \App\Http\Controllers\Odoo\OdooAccountController(app(\App\Services\OdooService::class)))
            //         ->syncPassword($idOdooUser, $newPassword);
            // })->afterResponse();

            return response()->json([
                'success' => true,
                'message' => 'Le mot de passe a été modifié avec succès.',
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['change-password', $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $currentToken = $user?->currentAccessToken();

        if ($currentToken) {
            DeviceToken::where('access_token_id', $currentToken->id)->delete();
            $currentToken->delete();
        }

        return response()->json([
            'status'  => 'success',
        ]);
    }

    static function customizeUserArray($user, $token)
    {
        return [
            'user'  => [
                'id'    => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'first_name_birth' => $user->first_name_birth,
                'date_of_birth' => $user->date_of_birth ? $user->date_of_birth->format('d/m/Y') : null,
                'phone' => $user->phone,

                'country' => $user->country,
                'country_code' => $user->country_code,
                'postal_code' => $user->postal_code,
                'city' => $user->city,

                'student_flg' => $user->student_flg,
                'expires_at' => $user->expires_at ? $user->expires_at->format('d/m/Y') : null,

                'establishment_id' => $user->establishment_id,
                'establishment' => $user->establishment ? $user->establishment->name : null,
                'orther_establishment' => $user->orther_establishment,

                'profession_id' => $user->profession_id,
                'profession' => $user->profession ? $user->profession->name : null,
                'orther_profession' => $user->orther_profession,

                'no_secu' => $user->no_secu,
                'funding_organization_id' => $user->funding_organization_id,
                'funding_organization' => $user->organization ? $user->organization->name : null,

                // Reset password
                'reset_password_flg' => $user->password_temp != null,

                // Company
                'has_company' => sizeof($user->companiesUserIsManager) > 0,
                'belongs_to_company' => sizeof($user->companiesUserIsMember) > 0,

                'closable_ads' => in_array($user->role->name, ['admin', 'directeur', 'manager']),

                // Subscription
                'has_subscription' => $user->hasSubscription(),

                // Settings
                'settings' => [
                    'notifications' => $user->notification_flg,
                    'topics' => $user->topic_ids,
                ],

                // Remind
                'remind_to_create_company' => (
                    $user->profession_id == 11 &&
                    !$user->companiesUserIsManager()->exists()
                ),
                'remind_manager_to_add_member' => (
                    $user->profession_id == 11
                    && $user->companiesUserIsManager()
                    ->whereDoesntHave('users', function ($query) {
                        $query->where('manager_flg', false);
                    })
                    ->exists()
                ),
                'remind_manager_to_add_package' => (
                    $user->companiesUserIsManager()->exists()
                    && $user->packages()->doesntExist()
                ),
                'remind_to_open_new_package' => (
                    !$user->packages()
                        ->wherePivot('progress', '<', 100)
                        ->exists()
                ),

                // Sync with old version
                'id_user' => $user->id_user,
                'id_odoo_partner' => $user->id_odoo_partner,
                'id_odoo_user' => $user->id_odoo_user,

                'tel' => $user->phone,
                "enabled" => 1,
                "job_function" => $user->profession ? $user->profession->name : null,
            ],
            'token' => [
                'jwt_token' => $token,
                'refresh_token' => $token, // Sync with old version
            ],
            'token_type' => 'Bearer',
        ];
    }

    public function storeDevice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|max:255',
            'device_id' => 'required|string|max:255',
            'device_type' => 'required|in:ios,android,web',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            // Update or create device token
            DeviceToken::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'device_id' => $request->device_id,
                ],
                [
                    'fcm_token' => $request->fcm_token,
                    'device_type' => $request->device_type,
                    'platform' => $request->platform,
                    'last_used_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['device-token']);
            return response()->json([
                'success' => false,
            ], 500);
        }
    }

    /**
     * Send Ads interest to API Old Server
     */
    public function sendAdsInterest(Request $request)
    {
        if (!$request->filled('destEmail') || !$request->filled('adTitle')) {
            return response()->json([
                'success' => false,
            ]);
        }

        $response = Http::post('https://api.orthomaster.fr/ads/interest', [
            'destination' => $request->destEmail,
            'user' => [
                'name'         => $request->user()->name . ' ' . $request->user()->first_name,
                'email'        => $request->user()->email,
                'job_function' => $request->user()->profession ? $request->user()->profession->name : '',
            ],
            'ad_title' => $request->adTitle,
        ]);

        if ($response->successful()) {
            return response()->json([
                'success' => false,
            ]);
        } else {
            return response()->json([
                'success' => false,
            ]);
        }
    }
}
