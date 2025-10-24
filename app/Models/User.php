<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use TCG\Voyager\Models\Role;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\Mail;

class User extends \TCG\Voyager\Models\User
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }

    public function company()
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }

    public function profession()
    {
        return $this->hasOne(Profession::class, 'id', 'profession_id');
    }

    public function establishment()
    {
        return $this->hasOne(Establishment::class, 'id', 'establishment_id');
    }

    public function organization()
    {
        return $this->hasOne(FundingOrganization::class, 'id', 'funding_organization_id');
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class);
    }

    public function carts() {
        return $this->hasMany(Cart::class);
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

        return $user ? $user->status : null;
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
            ->with(['role', 'company', 'organization'])
            ->first();
    }
}
