<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Odoo\OdooAccountController;
use App\Models\User;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        // Active Odoo user
        $odooAccountController = new OdooAccountController(app(OdooService::class));
        $odooAccountController->active($user);

        return response()->json([
            'status' => 'success',
            'reset' => $user->password_temp != null,
            'data'   => [
                'user'  => [
                    'id'    => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'id_user' => $user->id_user,
                    'id_odoo_partner' => $user->id_odoo_partner,
                    'id_odoo_user' => $user->id_odoo_user,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

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

        return response()->json([
            'status' => 'success',
            'message' => 'Le mot de passe a été modifié avec succès.',
        ]);
    }

    public function logout()
    {
        $user = Auth::user();

        // chỉ xoá token hiện tại
        $user->currentAccessToken()?->delete();

        return response()->json([
            'status'  => 'success',
        ]);
    }
}
