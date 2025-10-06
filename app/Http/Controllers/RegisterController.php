<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function index()
    {
        return view('landingpage.register');
    }

    public function reset()
    {
        return view('landingpage.reset', [
            'token' => 'asd',
            'email' => 'asdfasdf',
        ]);
    }

    public function checkEmail(Request $request)
    {
        try {
            // Invalid request
            if (!$request->has('email')) {
                return response()->json([], 404);
            }

            // Check duplicate
            if (User::isDuplicateEmail($request->email)) {
                return response()->json([
                    'success' => false,
                    'code' => 'email-exist',
                    'message' => "Cet email est déjà utilisé, veuillez en choisir un autre ou récupérer votre mot de passe."
                ]);
            }

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function submit(Request $request)
    {
        dd($request->all());

        // Validate dữ liệu
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|max:255',
            'upload_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // Max 5MB
        ], [
            'email.required' => 'L\'email est obligatoire',
            'email.email' => 'Le format de l\'email est invalide',
            'email.unique' => 'Cet email existe déjà dans le système',
            'name.required' => 'Le nom est obligatoire',
            'upload_file.mimes' => 'Le fichier doit être au format: jpg, jpeg, png, pdf',
            'upload_file.max' => 'Le fichier ne doit pas dépasser 2 Mo',
        ]);

        // Nếu validation thất bại
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Xử lý upload file nếu có
            $filePath = null;
            if ($request->hasFile('upload_file')) {
                $file = $request->file('upload_file');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('uploads', $fileName, 'public');
            }

            // Tạo user mới
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt('default_password'), // Thay đổi nếu có trường password
                'file_path' => $filePath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đăng ký thành công',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }
}
