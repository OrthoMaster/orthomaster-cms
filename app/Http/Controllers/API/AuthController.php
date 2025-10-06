<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        return response()->json([
            'status' => 'success',
            'data'   => [
                'user'  => [
                    'id'    => $user->id,
                    'email' => $user->email,
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
