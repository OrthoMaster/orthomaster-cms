<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CongressBoard extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_LIVE = 'live';
    public const STATUS_CLOSED = 'closed';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'closed_at'  => 'datetime',
    ];

    public function themes(): HasMany
    {
        return $this->hasMany(CongressBoardTheme::class, 'board_id')->orderBy('order_dsp')->orderBy('id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(CongressQuestion::class, 'board_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CongressParticipant::class, 'board_id');
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    /**
     * Les liens publics vivent sur auth.* quel que soit le domaine du CMS d'où ils sont générés.
     */
    public function readerUrl(): string
    {
        return rtrim(config('app.auth_url'), '/') . '/congres/' . $this->code;
    }

    /** Page où l'intervenant saisit son code. */
    public function operatorEntryUrl(): string
    {
        return rtrim(config('app.auth_url'), '/') . '/congres/operator';
    }

    public function operatorUrl(): string
    {
        return $this->operatorEntryUrl() . '/' . $this->operator_code;
    }

    public function screenUrl(): string
    {
        return $this->readerUrl() . '/ecran/' . $this->screen_token;
    }
}
