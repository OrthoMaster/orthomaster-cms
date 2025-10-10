<?php

namespace App\Http\Controllers;

use App\Mail\CreatedAccountMail;
use App\Models\Establishment;
use App\Models\NoProfType;
use App\Models\Profession;
use App\Models\User;
use App\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use TCG\Voyager\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userRole = Role::where('name', 'user')->first();

        $query = User::query();
        $query->where('role_id', $userRole->id);

        if ($request->filled('name')) {
            $query->where('name', 'LIKE', '%' . $request->name . '%');
        }
        if ($request->filled('first_name')) {
            $query->where('first_name', 'LIKE', '%' . $request->name . '%');
        }
        if ($request->filled('email')) {
            $query->where('email', 'LIKE', '%' . $request->name . '%');
        }

        if ($request->has('status')) {
            if ($request->status) {
                $query->where('status', $request->status);
            }
        } else {
            $query->where('status', Status::PENDING->value);
        }

        $users = $query->latest('id')->paginate(20);
        $users->appends($request->query());

        return view('accounts.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $estabs = Establishment::getAll();
        $professions = Profession::getAll();
        $noProfTypes = NoProfType::getAll();
        $edit = false;

        // Countries
        $path = public_path('countries.json');
        $json = file_get_contents($path);
        $countries = json_decode($json, true);

        return view(
            'accounts.edit-add',
            compact('edit', 'countries', 'estabs', 'professions', 'noProfTypes')
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate input data
        $validator = $request->validate([
            'email' => 'required|email|unique:users,email',
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
            'enrollment_certificate' => 'nullable|required_if:occupation,student|file|mimes:jpg,jpeg,png,pdf|max:5120', // Max 5MB
        ], [

            'email.required' => 'L\'email est obligatoire',
            'email.email' => 'Le format de l\'email est invalide',
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
            'enrollment_certificate.required_if' => 'Le certificat est obligatoire',
            'enrollment_certificate.mimes' => 'Le fichier doit être au format: jpg, jpeg, png, pdf',
            'enrollment_certificate.max' => 'Le fichier ne doit pas dépasser 5 Mo',
        ]);

        try {
            $userRole = Role::where('name', 'user')->first();
            $passwordTemp = Str::random(10);
            $isStudent = ($request->occupation == 'student');

            // Init profession
            $professionId = $isStudent ? $request->major : $request->job;
            $otherProfession = null;
            if ($professionId === 'other') {
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

                'no_prof_type_id' => (!$isStudent ? $request->rpps_type : null),
                'no_prof' => (!$isStudent ? $request->rpps : null),

                'status' => Status::PENDING->value
            ]);

            // Upload certificate
            if ($isStudent && $request->hasFile('enrollment_certificate')) {
                $imageController = new ImageController();
                $uploadResult = $imageController->uploadStudentCertificateToS3($user->id, $request->file('enrollment_certificate'));

                if ($uploadResult['success']) {
                    $user->certificate = $uploadResult['file']['url'];
                    $user->save();
                }
            }

            // Return success
            return redirect()->route('accounts.index');
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['admin', 'create-account', Auth::user()->id]);

            return back()->withInput()->withErrors([
                'system' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $user = User::findOrFail($id);
        $estabs = Establishment::getAll();
        $professions = Profession::getAll();
        $noProfTypes = NoProfType::getAll();
        $edit = true;

        // Countries
        $path = public_path('countries.json');
        $json = file_get_contents($path);
        $countries = json_decode($json, true);

        return view(
            'accounts.edit-add',
            compact('edit', 'user', 'countries', 'estabs', 'professions', 'noProfTypes')
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Active account.
     */
    public function confirm(string $id)
    {
        $user = User::findOrFail($id);
        try {
            $user->status = Status::ACTIVE->value;
            $user->save();

            // Send email
            Mail::to($user->email)->send(new CreatedAccountMail($user->email, $user));

            return back();
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['admin', 'confirm-account', auth()->user->id]);

            return back()->withErrors([
                'system' => $e->getMessage()
            ]);
        }
    }
}
