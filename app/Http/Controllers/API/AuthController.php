<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OldVersionUser;
use App\Models\User;
use App\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use TCG\Voyager\Models\Role;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Ces informations d\'identification ne correspondent pas à nos enregistrements.'
            ], 422);
        }

        $deviceName = $request->input('device', 'mobile');
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName)->plainTextToken;

        // Sync with old system
        if ($user->password_temp != null) {
            dispatch(function () use ($user) {
                (new \App\Http\Controllers\Odoo\OdooAccountController(app(\App\Services\OdooService::class)))
                    ->active($user);
            })->afterResponse();

            // Clear password temp
            $user->password_temp = null;
            $user->save();
        }

        // Tracking app open
        $agent = $request->agent;
        dispatch(function () use ($token, $agent) {
            app(\App\Http\Controllers\Tracking\TrkAppOpenController::class)
                ->tracking($token, $agent);
        })->afterResponse();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'reset' => $user->password_temp != null,
                'user'  => [
                    'id'    => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,

                    // Sync with old version
                    'id_user' => $user->id_user,
                    'id_odoo_partner' => $user->id_odoo_partner,
                    'id_odoo_user' => $user->id_odoo_user,
                    'office_id' => $user->companies()->first()?->office_id,
                    // 'office_id' => $user->company ? $user->company->office_id : null,
                    'tel' => $user->phone,
                    "enabled" => 1,
                    "job_function" => $user->profession ? $user->profession->name : null,
                ],
                'token' => [
                    'jwt_token' => $token,
                    'refresh_token' => $token, // Sync with old version
                ],
                'token_type' => 'Bearer',
            ],
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

            // Sync from Odoo
            dispatch(function () use ($oldVersionUser, $user) {
                (new \App\Http\Controllers\Odoo\OdooAccountController(app(\App\Services\OdooService::class)))
                    ->syncFromOdooToNewUser($oldVersionUser->id_odoo_partner, $user->id);
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
     * Get info of current auth user
     */
    public function me()
    {
        $user = Auth::user();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'user' => [
                    'id'    => $user->id,
                    'email' => $user->email,
                ],
            ],
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
        dispatch(function () use ($user, $request) {
            (new \App\Http\Controllers\Odoo\OdooAccountController(app(\App\Services\OdooService::class)))
                ->syncPassword($user->id_odoo_user, $request->new_password);
        })->afterResponse();

        return response()->json([
            'status' => 'success',
            'message' => 'Le mot de passe a été modifié avec succès.',
        ]);
    }

    public function logout()
    {
        $user = Auth::user();

        // Remove current token
        $user->currentAccessToken()?->delete();

        return response()->json([
            'status'  => 'success',
        ]);
    }
}
