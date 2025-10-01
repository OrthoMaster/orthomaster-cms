<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function index()
    {
        return view('landingpage.register');
    }

    public function checkEmail(Request $request)
    {
        try {
            // Invalid request
            if (!$request->has('email')) {
                return response()->json([], 404);
            }

            if (User::isDuplicateEmail($request->email)) {
                return response()->json([
                    'success' => false,
                    'code' => 'email.exist',
                    'message' => "Cet email est déjà utilisé, veuillez en choisir un autre ou récupérer votre mot de passe."
                ]);
            }

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }


        // TODO:...
    }

    public function submit(Request $request) {
        dd($request->all());
    }
}
