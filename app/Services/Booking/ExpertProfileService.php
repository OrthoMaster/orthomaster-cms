<?php

namespace App\Services\Booking;

use App\Models\Domain;
use App\Models\Expert;
use App\Support\Brochures\BrochureDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Profil public de l'expert, modifié depuis l'application : identité, présentation,
 * domaines et palette. Mêmes colonnes que la page vitrine : elle suit les changements.
 * La photo n'est pas modifiable ici (pas de sélecteur d'images dans l'application).
 */
class ExpertProfileService
{
    /** Longueurs du schéma (experts, domains). */
    public const HONORIFIC_MAX   = 40;
    public const NAME_MAX        = 255;
    public const CREDENTIALS_MAX = 200;
    public const LEDE_MAX        = 5000;
    public const DOMAIN_MAX      = 120;

    /**
     * @param array{honorific?:?string, name?:string, credentials?:?string, lede?:?string, palette?:?string, domain_ids?:array<int>} $input
     */
    public function update(Expert $expert, array $input): Expert
    {
        $data = Validator::make($input, [
            'honorific'    => ['nullable', 'string', 'max:' . self::HONORIFIC_MAX],
            'name'         => ['required', 'string', 'max:' . self::NAME_MAX],
            'credentials'  => ['nullable', 'string', 'max:' . self::CREDENTIALS_MAX],
            'lede'         => ['nullable', 'string', 'max:' . self::LEDE_MAX],
            // null = couleur par défaut de l'application.
            'palette'      => ['nullable', 'string', Rule::in(array_keys(BrochureDraft::PALETTES))],
            'domain_ids'   => ['nullable', 'array'],
            'domain_ids.*' => ['integer', 'exists:domains,id'],
        ], [
            'name.required' => 'Le nom est obligatoire.',
            'palette.in'    => 'Couleur inconnue.',
        ], [
            'honorific'   => 'titre',
            'name'        => 'nom',
            'credentials' => 'références',
            'lede'        => 'présentation',
        ])->validate();

        $clean = fn (?string $value) => ($value = trim((string) $value)) === '' ? null : $value;

        DB::transaction(function () use ($expert, $data, $clean) {
            // forceFill : ne dépend pas du $fillable du modèle Expert (géré ailleurs, par la vitrine).
            $expert->forceFill([
                'honorific'   => $clean($data['honorific'] ?? null),
                'name'        => trim($data['name']),
                'credentials' => $clean($data['credentials'] ?? null),
                'lede'        => $clean($data['lede'] ?? null),
                'palette'     => $data['palette'] ?? null,
            ])->save();

            // Ordre choisi par l'expert conservé dans la position du pivot.
            $ids  = array_values(array_unique(array_map('intval', $data['domain_ids'] ?? [])));
            $sync = [];
            foreach ($ids as $position => $id) {
                $sync[$id] = ['position' => $position + 1];
            }
            $expert->domains()->sync($sync);
        });

        return $expert->fresh('domains');
    }

    /**
     * Ajoute un domaine à la liste commune (sans validation, décision produit) ;
     * renvoie l'existant si le libellé est déjà présent, à la casse près.
     */
    public function createDomain(?string $label): Domain
    {
        $label = trim(preg_replace('/\s+/', ' ', (string) $label));

        if ($label === '') {
            throw ValidationException::withMessages(['label' => 'Indiquez le nom du domaine.']);
        }
        if (mb_strlen($label) > self::DOMAIN_MAX) {
            throw ValidationException::withMessages(['label' => 'Le domaine ne doit pas dépasser ' . self::DOMAIN_MAX . ' caractères.']);
        }

        $existing = Domain::whereRaw('LOWER(label) = ?', [mb_strtolower($label)])->first();
        if ($existing) {
            return $existing;
        }

        $domain = new Domain();
        $domain->forceFill([
            'label'      => $label,
            'sort_order' => (int) Domain::max('sort_order') + 1,
        ])->save();

        return $domain;
    }
}