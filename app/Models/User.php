<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Http\Controllers\Odoo\OdooAccountController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use TCG\Voyager\Models\Role;
use App\Mail\ResetPasswordMail;
use App\Services\OdooService;
use App\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends \TCG\Voyager\Models\User
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $guarded = ['id'];
    protected $appends = ['missing_fields_message', 'topic_ids'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'expires_at' => 'datetime',
            'notification_flg' => 'boolean'
        ];
    }

    public function getTopicIdsAttribute()
    {
        return $this->topics->pluck('id')->toArray();
    }

    public function oldVersion()
    {
        return $this->hasOne(OldVersionUser::class, 'id_user', 'id_user');
    }

    public function role()
    {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }

    public function companiesUserIsMember()
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('manager_flg')
            ->wherePivot('manager_flg', false);
    }

    public function companiesUserIsManager()
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('manager_flg')
            ->wherePivot('manager_flg', true);
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('manager_flg')
            ->withTimestamps();
    }

    public function profession()
    {
        return $this->hasOne(Profession::class, 'id', 'profession_id');
    }

    public function establishment()
    {
        return $this->hasOne(Establishment::class, 'id', 'establishment_id');
    }

    public function noProfType()
    {
        return $this->hasOne(NoProfType::class, 'id', 'no_prof_type_id');
    }

    public function organization()
    {
        return $this->hasOne(FundingOrganization::class, 'id', 'funding_organization_id');
    }

    public function createdBy()
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }

    public function notifications()
    {
        return $this->hasMany(SystemNotification::class, 'user_id', 'id')
            ->with('actor');
    }

    public function topics()
    {
        return $this->belongsToMany(Topic::class);
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_user')
            ->withPivot('progress')
            ->withTimestamps();
    }

    public function lessons()
    {
        return $this->belongsToMany(Lesson::class)
            ->withPivot('completed_flg', 'metadata')
            ->withTimestamps();
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function devices()
    {
        return $this->hasMany(DeviceToken::class);
    }

    protected function missingFieldsMessage(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->funding_organization_id) {
                    return 'Manquant: Organisme de formation.';
                }

                $errorMessage = '';

                // Opco-ep
                if ($this->function_organization_id === 1) {
                    if (!$this->date_of_birth) {
                        $errorMessage += ($errorMessage ? ', ' : '') + 'Date de naissance';
                    }

                    if (!$this->no_secu) {
                        $errorMessage += ($errorMessage ? ', ' : '') + 'N° SECU';
                    }
                }

                // FIFPL
                if ($this->function_organization_id === 2) {
                    if (!$this->date_of_birth) {
                        $errorMessage += ($errorMessage ? ', ' : '') + 'Date de naissance';
                    }
                }

                if ($errorMessage) {
                    return 'Manquant: ' . $errorMessage;
                }

                return null;
            }
        );
    }

    /**
     * Send email reset password notification
     */
    public function sendPasswordResetNotification($token)
    {
        Mail::to($this->email)->send(new ResetPasswordMail($token, $this->email));
    }

    /**
     * Check email
     */
    public static function checkRegEmail(string $email)
    {
        $user = User::where('email', $email)->first();

        // If exist
        if ($user) {
            return $user->status;
        }

        // Sync with old version
        $exists = OldVersionUser::where('email', $email)->exists();
        // Email is used
        if ($exists) {
            return Status::ACTIVE->value;
        }

        // Email does not existsUser
        return null;
    }

    /**
     * Get all admins
     */
    public static function getAdmins()
    {
        return User::whereIn('role_id', function ($query) {
            $query->select('role_id')
                ->distinct()
                ->from('permission_role')
                ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
                ->where('permissions.key', 'browse_admin');
        })->with('role')->get();
    }

    public static function findById($id)
    {
        return User::where('id', $id)
            ->with(['role', 'companiesUserIsMember', 'organization'])
            ->first();
    }

    public static function findByEmail($email)
    {
        return User::where('email', $email)->first();
    }

    /**
     * Create old version DB - table user with data from users
     */
    public static function syncWithOldVersionDB($id)
    {
        $user = User::find($id);
        if ($user->id_user) {
            return true;
        }

        $exOldVersionUser = OldVersionUser::create([
            'name' => $user->name . ' ' . $user->first_name,
            'email' => $user->email,
            'is_student' => $user->student_flg,
            'id_odoo_partner' => $user->id_odoo_partner,
            'id_odoo_user' => $user->id_odoo_user,
        ]);
        $user->id_user = $exOldVersionUser->id_user;
        $user->save();
        return true;
    }

    public static function syncFromOldVersion($email)
    {
        // Check did sync
        $user = User::where('email', $email)->first();
        if ($user) {
            return true;
        };

        try {
            // Get old version
            $oldVersionUser = OldVersionUser::where('email', $email)->first();
            if (!$oldVersionUser) {
                return false;
            }

            $userRole = Role::where('name', 'user')->first();
            $passwordTemp = Str::random(10);
            $user = User::create([
                'role_id' => $userRole->id,
                'name' => mb_convert_case(trim($oldVersionUser->name), MB_CASE_TITLE, 'UTF-8'),
                'email' => $oldVersionUser->email,
                'password_temp' => $passwordTemp,
                'password' => bcrypt($passwordTemp),
                'phone' => $oldVersionUser->tel && $oldVersionUser->tel !== '0' ? $oldVersionUser->tel : null,
                'student_flg' => $oldVersionUser->is_student,

                'created_from' => 'CLONE',
                'id_odoo_partner' => $oldVersionUser->id_odoo_partner,
                'id_odoo_user' => $oldVersionUser->id_odoo_user,
                'id_user' => $oldVersionUser->id_user,
                'status' => Status::ACTIVE->value
            ]);

            // Clone other infos from Odoo
            $odooAccountController = new OdooAccountController(app(OdooService::class));
            $contact = $odooAccountController->getContactInfo($oldVersionUser->id_odoo_partner);
            if ($contact) {
                // Get Profession
                if (!empty($contact['x_studio_fonction_professionnelle_1'])) {
                    $prof = Profession::findByIdOdoo($contact['x_studio_fonction_professionnelle_1'][0]);
                    if ($prof) {
                        $user->profession_id = $prof->id;
                    }
                }

                // Get Estab
                if (!empty($contact['x_studio_type_dtablissement_1'])) {
                    $estab = Establishment::findByIdOdoo($contact['x_studio_type_dtablissement_1'][0]);
                    if ($estab) {
                        $user->establishment_id = $estab->id;
                    }
                }

                $user->save();
            }

            return true;
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['sync-user', 'odoo']);
            return false;
        }
    }

    public static function syncOldVersionCompany($userId, $oldVersionUserId)
    {
        $user = User::find($userId);
        $oldVersionUser = OldVersionUser::find($oldVersionUserId);

        if (!$user || !$oldVersionUser) {
            return false;
        }
        try {
            // Search in relationship with office
            $belongOffices = DB::table('user_belong_to_office')
                ->where('id_user', $oldVersionUser->id_user)
                ->get();

            foreach ($belongOffices as $belong) {
                $office = OldVersionCompany::where('id_office', $belong->id_office)->first();
                if ($office && $office->id_odoo_partner) {
                    $estab = Establishment::where('name', 'LIKE', $office->structure)->first();
                    $company = Company::firstOrCreate(
                        ['id_odoo' => $office->id_odoo_partner],
                        [
                            'name' => $office->name,
                            'email' => $office->email,
                            'address' => $office->address_way,
                            'postal_code' => $office->address_postal_code,
                            'city' => $office->address_city,
                            'country' => $office->address_country,
                            'phone' => $office->tel,
                            'establishement_id' => ($estab && $office->structure ? $estab->id : null)
                        ]
                    );

                    $user->companies()->syncWithoutDetaching([
                        $company->id => ['manager_flg' => $belong->is_office_manager]
                    ]);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['sync-company', 'odoo']);
            return false;
        }
    }

    public static function syncOldVersionTraining($userId, $oldVersionUserId)
    {
        $regFormations = DB::table('user_registered_formations')
            ->where('id_user', $oldVersionUserId)
            ->get();
        if ($regFormations->isEmpty()) {
            return false;
        }

        $user = User::with('packages')->find($userId);
        if (!$user || $user->packages->isNotEmpty()) {
            return false;
        }

        try {
            $trainingIds = [];
            foreach ($regFormations as $formation) {
                switch ($formation->id_formation) {
                    case 1:
                        array_push($trainingIds, 1, 3, 4, 5);
                        break;
                    case 2:
                        array_push($trainingIds, 13);
                        break;
                    case 3:
                        array_push($trainingIds, 2);
                        break;
                    case 4:
                        array_push($trainingIds, 14);
                        break;
                    case 6:
                        array_push($trainingIds, 5);
                        break;
                    default:
                        break;
                }
            }

            $user->packages()->sync($trainingIds);
            return true;
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['sync-company', 'odoo']);
            return false;
        }
    }
}
