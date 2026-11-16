<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Single-use code: the OrthoMaster app opens SCAT6 with it, scat-server redeems it once. */
class ScatHandoffCode extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Creates a code for the user and returns the plain value (only time it exists in clear). */
    public static function issueFor(User $user): string
    {
        // Old codes of this user are useless once a new one is asked.
        static::where('user_id', $user->id)->whereNull('used_at')->delete();

        $code = Str::random(48);
        static::create([
            'user_id' => $user->id,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addSeconds(config('scat.handoff_ttl', 60)),
        ]);

        return $code;
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
