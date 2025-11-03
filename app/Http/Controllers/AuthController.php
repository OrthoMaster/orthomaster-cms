<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Odoo\OdooAccountController;
use App\Models\Establishment;
use App\Models\HealthcareProfessional;
use App\Models\InviteCode;
use App\Models\NoProfType;
use App\Models\Profession;
use App\Models\User;
use App\Services\OdooService;
use App\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use TCG\Voyager\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /**
     * View register page
     */
    public function register(Request $request)
    {
        $estabs = Establishment::getAll();
        $professions = Profession::getAll();
        $noProfTypes = NoProfType::getAll();

        // Countries
        $path = public_path('countries.json');
        $json = file_get_contents($path);
        $countries = json_decode($json, true);

        // Invite code
        $invCode = null;
        if ($request->has('inviteCode')) {
            $checkInviteCode = InviteCode::checkCode($request->inviteCode);
            if ($checkInviteCode == 'code-valid') {
                $invCode = $request->inviteCode;
            }
        }

        return view(
            'landingpage.register',
            compact('estabs', 'professions', 'noProfTypes', 'countries', 'invCode')
        );
    }

    /**
     * Handle submit register form
     */
    public function submit(Request $request)
    {
        // Validate input data
        $validator = Validator::make($request->all(), [
            'email' => 'required|email:rfc,strict|unique:users,email|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'phone' => 'nullable|regex:/^\+?[0-9]{1,3}[\s\-]?(\(?\d{1,4}\)?[\s\-]?){1,}[0-9]{1,}$/|min:10|max:20',
            'occupation' => 'required|in:professor,student',

            'country' => 'nullable|max:3',
            'postal_code' => 'nullable|max:10',
            'city' => 'nullable|max:30',

            // Professor
            'estab' => 'required_if:occupation,professor',
            'other_estab' => 'nullable|required_if:estab,other|string|max:100',
            'job' => 'required_if:occupation,professor',
            'other_job' => 'nullable|required_if:job,other|string|max:100',
            'rpps' => 'nullable|string|max:100',

            // Student
            'major' => 'required_if:occupation,student',
            'other_major' => 'nullable|required_if:major,other|string|max:100',
            'enrollment_certificate' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // Max 5MB
        ], [

            'email.required' => 'L\'email est obligatoire',
            'email.email' => 'Le format de l\'email est invalide',
            'email.regex' => 'Le format de l\'email est invalide',
            'email.unique' => 'Cet email existe déjà dans le système',

            'name.required' => 'Le nom est obligatoire',

            'first_name.required' => 'Le prénom est obligatoire',
            'occupation.required' => 'Le profession est obligatoire',
            'occupation.in' => 'Le profession est invalide',

            'estab.required_if' => "Le type d'établissement est obligatoire",
            'other_estab.required_if' => "Le type d'établissement est obligatoire",
            'job.required_if' => "La fonction professionelle est obligatoire",
            'other_job.required_if' => "La fonction professionelle est obligatoire",
            'rpps.required_if' => "Le numéro professionnel est obligatoire",

            'major.required_if' => "La spécialité est obligatoire",
            'other_major.required_if' => "La spécialité est obligatoire",
            'enrollment_certificate.mimes' => 'Le fichier doit être au format: jpg, jpeg, png, pdf',
            'enrollment_certificate.max' => 'Le fichier ne doit pas dépasser 5 Mo',
        ]);

        // Validation failed
        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userRole = Role::where('name', 'user')->first();
            $passwordTemp = Str::random(10);
            $isStudent = ($request->occupation == 'student');

            // Check if has invite code
            $checkInviteCode = null;
            if ($request->has('invite_code')) {
                $checkInviteCode = InviteCode::checkCode($request->invite_code);
                if ($checkInviteCode == 'code-valid') {
                    InviteCode::increaseUsedTimes($request->invite_code);
                }
            }

            // Check RPPS if not invite code
            $checkedRpps = ($checkInviteCode == 'code-valid');
            if (!$isStudent && !$checkedRpps) {
                $checkedRpps = HealthcareProfessional::checkExist($request->rpps, $request->name, $request->first_name);
            }

            // Init profession
            $professionId = $isStudent ? $request->major : $request->job;
            $otherProfession = null;
            if ($professionId == 'other') {
                $professionId = null;
                $otherProfession = $isStudent ? $request->other_major : $request->other_job;
            }

            // Init establishment
            $estabId = $isStudent ? null : $request->estab;
            $otherEstab = null;
            if ($estabId == 'other') {
                $estabId = null;
                $otherEstab = $request->other_estab;
            }

            // Init no prof
            $noProfType = $isStudent ?  null : $request->rpps_type;
            $noProf = null;
            if ($noProfType !== 'other') {
                $noProf = $request->rpps;
            }

            // Condition active
            $isActive = (!$isStudent && $checkedRpps) || ($checkInviteCode == 'code-valid');

            // Create new user
            $user = User::create([
                'role_id' => $userRole->id,
                'name' => mb_convert_case(trim($request->name), MB_CASE_TITLE, 'UTF-8'),
                'first_name' => mb_convert_case(trim($request->first_name), MB_CASE_TITLE, 'UTF-8'),
                'email' => $request->email,
                'password_temp' => $passwordTemp,
                'password' => bcrypt($passwordTemp),
                'phone' => $request->phone,
                'country_code' => $request->country,
                'postal_code' => trim($request->postal_code),
                'city' => mb_convert_case(trim($request->city), MB_CASE_TITLE, 'UTF-8'),

                'student_flg' => $isStudent,

                'establishment_id' => $estabId,
                'other_establishment' => $otherEstab,
                'profession_id' => $professionId,
                'other_profession' => $otherProfession,

                'no_prof_type_id' => $noProfType,
                'no_prof' => $noProf,

                'invite_code' => $request->invite_code,

                'created_from' => $request->inapp && $request->inapp == 'WEB' ? 'WEB' : 'APP',
                'status' => ($isActive ? Status::ACTIVE->value : Status::PENDING->value)
            ]);

            // Upload certificate
            if ($request->hasFile('enrollment_certificate')) {
                $imageController = new ImageController();
                $uploadResult = $imageController->uploadStudentCertificateToS3($user->id, $request->file('enrollment_certificate'));

                if ($uploadResult['success']) {
                    $user->certificate = $uploadResult['file']['url'];
                    $user->save();
                }
            }

            // Send email and notification
            MailController::createdNewAccount($user);

            // Sync with Odoo
            if ($isActive) {
                $odooAccountController = new OdooAccountController(app(OdooService::class));
                $odooAccountController->store($user);
            }

            // Return success
            return response()->json([
                'status' => ($isActive ? 'success' : 'pending'),
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['register', $request->except(['token', 'enrollment_certificate'])]);

            return response()->json([
                'status' => 'failed',
                'errors' => ['Une erreur est survenue. Veuillez réessayer !']
            ], 500);
        }
    }

    /**
     * View forgot password page
     */
    public function viewForgotPage(Request $request)
    {
        return view('landingpage.password-request', ['email' => $request->email]);
    }

    /**
     * Handle sent link reset password
     */
    public function sendResetLink(Request $request)
    {
        // Check honeypot - anti autobot
        if ($request->filled('website')) {
            Log::warning('Autobot attack IP: ' . $request->ip(), ['password', 'attack', 'autobot']);

            return response()->json(['status' => 'success'], 200);
        }

        // Anti spam - throttle with email
        $throttleKey = Str::lower($request->input('email')) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            return response()->json([
                'status' => 'failed',
                'error' => __('auth.throttle', ['second' => '180'])
            ], 429);
        };

        // Validate email
        $validator = Validator::make($request->all(), [
            'email' => 'required|email:rfc,strict'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'error' => $validator->errors()
            ], 422);
        };


        $user = User::where('email', $request->email)->first();
        $sendableEmail = true;

        if ($user) {
            // Pending user - checking
            if ($user->status == Status::PENDING->value) {
                $sendableEmail = false;
            }
        } else {
            // Sync with old version
            $sendableEmail = User::syncFromOldVersion($request->email);
        }

        // No send link
        if (!$sendableEmail) {
            return response()->json(['status' => 'success'], 200);
        }

        // Send link
        $status = Password::sendResetLink($request->only('email'));

        // Hit 1 request
        RateLimiter::hit($throttleKey, 180);

        // Check status
        if ($status === Password::RESET_LINK_SENT) {
            Log::channel('mail')->info('Sent reset link: ' . $request->email, ['password']);
        } else {
            Log::channel('mail')->error('Sent reset link ' . $request->email, ['password', $status]);
        }

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * View update new password
     */
    public function viewResetPage(Request $request)
    {
        $status = 'valid';
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);
        if ($validator->fails()) {
            $status = 'failed-validate';
        }

        $token = $request->token;
        $email = $request->email;

        $user = User::where('email', $email)->first();
        if (!$user) {
            $status = 'failed-check-exist';
        }

        $broker = Password::broker();
        $tokenRepository = $broker->getRepository();
        if (!$tokenRepository->exists($user, $token)) {
            $status = 'failed-check-token';
        }

        return view('landingpage.password-reset', [
            'status' => $status,
            'token' => $request->token,
            'email' => $request->email,
        ]);
    }

    /**
     * Reset password
     */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'Veuillez saisir votre adresse e-mail.',
            'email.email' => 'Adresse e-mail invalide.',
            'password.required' => 'Veuillez saisir un nouveau mot de passe.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        // Validation
        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Reset password
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->password_temp = null; // Clear password temp
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'success',
                'message' => 'Votre mot de passe a été réinialisé avec succès !'
            ], 200);
        }

        // Handle errors
        $errorMessages = [
            Password::INVALID_TOKEN => 'Le lien de réinitialisation est invalide ou a expiré.',
            Password::INVALID_USER => 'Utilisateur non trouvé.',
            Password::RESET_THROTTLED => 'Veuillez patienter avant de réessayer.',
        ];

        return response()->json([
            'status' => 'failed',
            'errors' => ['email' => [$errorMessages[$status] ?? 'Une erreur s\'est produite.']]
        ], 422);
    }

    /**
     * Check email for register
     */
    public function checkEmail(Request $request)
    {
        try {
            // Invalid request
            if (!$request->has('email')) {
                return response()->json([], 404);
            }

            // Check register email
            $checkRegEmail = User::checkRegEmail($request->email);

            // Valid
            if ($checkRegEmail === null) {
                // Add check invite code
                $inviteCode = $request->inviteCode;

                // No invite code
                if (!$inviteCode) {
                    return response()->json([
                        'status' => 'success',
                    ]);
                }

                $checkInviteCode = InviteCode::checkCode($inviteCode);
                return response()->json([
                    'status' => ($checkInviteCode === 'code-valid') ? 'success' : 'failed',
                    'code' => $checkInviteCode,
                    'message' => "Le code est incorrect ou a expiré."
                ]);
            }

            // Pending account
            if ($checkRegEmail === Status::PENDING->value) {
                return response()->json([
                    'status' => 'failed',
                    'code' => 'pending-account',
                    'message' => "Votre inscription avec cet e-mail est en cours de vérification. Veuillez patienter jusqu'à la fin du processus de validation."
                ]);
            }

            // Exist
            return response()->json([
                'status' => 'failed',
                'code' => 'email-exist',
                'message' => "Cet email est déjà utilisé, veuillez en choisir un autre ou récupérer votre mot de passe."
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
