<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Odoo\OdooAccountController;
use App\Mail\CreatedAccountMail;
use App\Models\Company;
use App\Models\Establishment;
use App\Models\FundingOrganization;
use App\Models\NoProfType;
use App\Models\Profession;
use App\Models\User;
use App\Services\OdooService;
use App\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use TCG\Voyager\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userRoles = Role::where('name', 'user')->orWhere('name', 'user-manager')->get()->pluck('id')->toArray();

        $query = User::query();
        $query->whereIn('role_id', $userRoles);

        if ($request->filled('name')) {
            $query->where('name', 'LIKE', '%' . $request->name . '%');
        }
        if ($request->filled('first_name')) {
            $query->where('first_name', 'LIKE', '%' . $request->first_name . '%');
        }
        if ($request->filled('email')) {
            $query->where('email', 'LIKE', '%' . $request->email . '%');
        }

        if ($request->has('status')) {
            if ($request->status !== 'all') {
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
    public function create(Request $request)
    {
        $estabs = Establishment::getAll();
        $professions = Profession::getAll();
        $noProfTypes = NoProfType::getAll();
        $organizations = FundingOrganization::getAll();
        $companies = Company::getAll();
        $edit = false;

        // Countries
        $path = public_path('countries.json');
        $json = file_get_contents($path);
        $countries = json_decode($json, true);

        // Stored company
        $storedCompany = null;
        $storedCompanyId = null;
        if ($request->has('company')) {
            $storedCompany = Company::findById($request->company);
            if ($storedCompany) {
                $storedCompanyId = $storedCompany->id;
            }
        }

        return view(
            'accounts.edit-add',
            compact(
                'edit',
                'countries',
                'estabs',
                'professions',
                'noProfTypes',
                'organizations',
                'companies',
                'storedCompany',
                'storedCompanyId'
            )
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate input data
        $validator = $request->validate([
            'email' => 'required|email:rfc,strict|unique:users,email',
            'name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'phone' => 'nullable|regex:/^\+?[0-9]{1,3}[\s\-]?(\(?\d{1,4}\)?[\s\-]?){1,}[0-9]{1,}$/|min:10|max:20',
            'occupation' => 'required|in:professor,student',

            'date_of_birth' => 'nullable|date_format:Y-m-d',
            'first_name_birth' => 'nullable|string|max:100',

            'country' => 'nullable|max:3',
            'postal_code' => 'nullable|max:10',
            'city' => 'nullable|max:30',

            // Professor
            'other_estab' => 'nullable|required_if:estab,other|string|max:100',
            'other_job' => 'nullable|required_if:job,other|string|max:100',
            'rpps' => 'nullable|string|max:100',

            // Student
            'enrollment_certificate' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // Max 5MB
        ], [
            'email.required' => 'L\'email est obligatoire',
            'email.email' => 'Le format de l\'email est invalide',
            'email.unique' => 'Cet email existe déjà dans le système',

            'name.required' => 'Le nom est obligatoire',

            'first_name.required' => 'Le prénom est obligatoire',
            'occupation.required' => 'Le profession est obligatoire',
            'occupation.in' => 'Le profession est invalide',

            'other_estab.required_if' => "Le type d'établissement est obligatoire",
            'other_job.required_if' => "La fonction professionelle est obligatoire",

            'enrollment_certificate.mimes' => 'Le fichier doit être au format: jpg, jpeg, png, pdf',
            'enrollment_certificate.max' => 'Le fichier ne doit pas dépasser 5 Mo',
        ]);

        try {
            $userRole = Role::where('name', 'user')->first();
            $passwordTemp = Str::random(10);
            $isStudent = ($request->occupation == 'student');

            // Init profession
            $professionId = $request->job;
            $otherProfession = null;
            if ($professionId === 'other') {
                $professionId = null;
                $otherProfession = $request->other_job;
            }

            // Init establishment
            $estabId = $request->estab;
            $otherEstab = null;
            if ($estabId == 'other') {
                $estabId = null;
                $otherEstab = $request->other_estab;
            }

            // Init no prof
            $noProfType = $request->rpps_type;
            $noProf = $request->rpps;
            if ($noProfType == 'other') {
                $noProfType = null;
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

                'no_prof_type_id' => $noProfType,
                'no_prof' => $noProf,

                'status' => Status::PENDING->value,


                'funding_organization_id' => $request->organization,
                'first_name_birth' => mb_convert_case(trim($request->first_name_birth), MB_CASE_TITLE, 'UTF-8'),
                'date_of_birth' => $request->date_of_birth ? $request->date_of_birth : null,
                'no_secu' => $request->no_secu,

                'created_by' => Auth::user()->id,
            ]);

            // Add to company
            if ($request->company) {
                $user->companies()->attach($request->company, ['manager_flg' => false]);
            }

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
            return redirect()->route('account.edit', $user->id)->with([
                'message' => 'Nouveau utilisateur créé avec succès',
                'alert-type' => 'success'
            ]);
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
        $user = User::findById($id);
        if (!$user) {
            return abort(404);
        }

        $estabs = Establishment::getAll();
        $professions = Profession::getAll();
        $noProfTypes = NoProfType::getAll();
        $organizations = FundingOrganization::getAll();
        $companies = Company::getAll();
        $edit = true;

        // Countries
        $path = public_path('countries.json');
        $json = file_get_contents($path);
        $countries = json_decode($json, true);

        // Company is member
        $storedCompany = null;
        $storedCompanyId = null;
        if ($user->companiesUserIsMember->isNotEmpty()) {
            $storedCompany = $user->companiesUserIsMember()->first();
            $storedCompanyId = $user->companiesUserIsMember()->first()->id;
        }

        // Responsable
        $admins = User::getAdmins();

        return view(
            'accounts.edit-add',
            compact(
                'edit',
                'user',
                'countries',
                'estabs',
                'professions',
                'noProfTypes',
                'organizations',
                'companies',
                'storedCompany',
                'storedCompanyId',
                'admins'
            )
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $validator = $request->validate([
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id), // Ignore current user
            ],
            'name' => 'required|string|max:100',
            'first_name' => 'nullable|string|max:100',
            'phone' => 'nullable|regex:/^\+?[0-9]{1,3}[\s\-]?(\(?\d{1,4}\)?[\s\-]?){1,}[0-9]{1,}$/|min:10|max:20',
            'occupation' => 'required|in:professor,student',

            'date_of_birth' => 'nullable|date_format:Y-m-d',
            'first_name_birth' => 'nullable|string|max:100',

            'country' => 'nullable|max:3',
            'postal_code' => 'nullable|max:10',
            'city' => 'nullable|max:30',

            // Professor
            'other_estab' => 'nullable|required_if:estab,other|string|max:100',
            'other_job' => 'nullable|required_if:job,other|string|max:100',
            'rpps' => 'nullable|string|max:100',

            // Student
            'enrollment_certificate' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // Max 5MB
        ], [
            'email.required' => 'L\'email est obligatoire',
            'email.email' => 'Le format de l\'email est invalide',
            'email.unique' => 'Cet email existe déjà dans le système',

            'name.required' => 'Le nom est obligatoire',

            'first_name.required' => 'Le prénom est obligatoire',
            'occupation.required' => 'Le profession est obligatoire',
            'occupation.in' => 'Le profession est invalide',

            'other_estab.required_if' => "Le type d'établissement est obligatoire",
            'other_job.required_if' => "La fonction professionelle est obligatoire",

            'enrollment_certificate.mimes' => 'Le fichier doit être au format: jpg, jpeg, png, pdf',
            'enrollment_certificate.max' => 'Le fichier ne doit pas dépasser 5 Mo',
        ]);


        $isStudent = ($request->occupation == 'student');
        // Init profession
        $professionId = $request->job;
        $otherProfession = null;
        if ($professionId === 'other') {
            $professionId = null;
            $otherProfession = $request->other_job;
        }

        // Init establishment
        $estabId = $request->estab;
        $otherEstab = null;
        if ($estabId == 'other') {
            $estabId = null;
            $otherEstab = $request->other_estab;
        }

        // Init no prof
        $noProfType = $request->rpps_type;
        $noProf = $request->rpps;
        if ($noProfType == 'other') {
            $noProfType = null;
        }

        try {
            // Create new user
            $user->update([
                'name' => mb_convert_case(trim($request->name), MB_CASE_TITLE, 'UTF-8'),
                'first_name' => mb_convert_case(trim($request->first_name), MB_CASE_TITLE, 'UTF-8'),
                'email' => $request->email,
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

                'funding_organization_id' => $request->organization,
                'first_name_birth' => mb_convert_case(trim($request->first_name_birth), MB_CASE_TITLE, 'UTF-8'),
                'date_of_birth' => $request->date_of_birth ? $request->date_of_birth : null,
                'no_secu' => $request->no_secu,

                'created_by' => $request->created_by
            ]);

            // Sync to company
            if ($request->company) {
                $user->companies()->syncWithoutDetaching($request->company);
            }

            // Upload certificate
            if ($isStudent && $request->hasFile('enrollment_certificate')) {
                $imageController = new ImageController();
                $uploadResult = $imageController->uploadStudentCertificateToS3($user->id, $request->file('enrollment_certificate'));

                if ($uploadResult['success']) {
                    $user->certificate = $uploadResult['file']['url'];
                    $user->save();
                }
            }

            return back();
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['admin', 'create-account', Auth::user()->id]);

            return back()->withInput()->withErrors([
                'system' => $e->getMessage()
            ]);
        }
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

            $odooAccountController = new OdooAccountController(app(OdooService::class));
            $odooAccountController->store($user);

            // Send email
            Log::channel('mail')->info('After registered: ' . $user->email, ['register']);
            Mail::to($user->email)->send(new CreatedAccountMail($user->email, $user));

            return back()->with([
                'message' => 'Mis à jour avec succès',
                'alert-type' => 'success'
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['admin', 'confirm-account', auth()->user->id]);

            return back()->withErrors([
                'system' => $e->getMessage()
            ]);
        }
    }

    public function resend(string $id)
    {
        $user = User::find($id);
        if ($user && $user->password_temp) {
            Mail::to($user->email)->send(new CreatedAccountMail($user->email, $user));
            Log::channel('mail')->info('Resend reg info: ' . $user->email, ['register']);
        }

        return back()->with([
            'message' => 'Réenvoyé avec succès',
            'alert-type' => 'success'
        ]);
    }


    public function sync(string $id)
    {
        $user = User::find($id);
        if ($user->id_odoo_partner && $user->id_odoo_user) {
            return back()->with([
                'message' => 'Impossible de mettre à jour',
                'alert-type' => 'error'
            ]);
        }

        $odooAccountController = new OdooAccountController(app(OdooService::class));
        $storedOdooUser = $odooAccountController->store($user);

        if ($storedOdooUser) {
            return back()->with([
                'message' => 'Modifié avec succès',
                'alert-type' => 'success'
            ]);
        }

        return back()->with([
            'message' => 'Erreur',
            'alert-type' => 'error'
        ]);
    }

    // Delete
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('accounts.index')->with([
            'message' => 'Supprimer avec succès',
            'alert-type' => 'success'
        ]);
    }
}
