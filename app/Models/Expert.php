<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Expert extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'brochure'              => 'array',     // version publiée : lieux, liens, titres, formation, parcours
            'brochure_published_at' => 'datetime',
        ];
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn () => $this->avatar
            ? Storage::disk('s3')->url($this->avatar)
            : null);
    }

    public function videos()
    {
        return $this->belongsToMany(Video::class);
    }

    /** Domaines d'expertise (table domain_expert), dans l'ordre d'affichage. */
    public function domains()
    {
        return $this->belongsToMany(Domain::class)
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /** Brouillon de la page partenaire (/demo/{slug}). */
    public function brochureDraft()
    {
        return $this->hasOne(ExpertBrochureDraft::class);
    }
}