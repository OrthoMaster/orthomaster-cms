<?php

namespace App\Services\Booking;

use App\Models\Domain;
use App\Models\Expert;
use App\Support\Brochures\BrochureDraft;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Profil public de l'expert, modifié depuis l'application : identité, présentation,
 * domaines et palette. Mêmes colonnes que la page vitrine : elle suit les changements.
 * La photo se change depuis la version web de l'application (sélecteur de fichier du
 * navigateur) ; la version mobile n'a pas de sélecteur d'images.
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
     * Titres proposés (liste fermée) : forme complète => abréviation.
     * La colonne garde la forme complète (comme la page vitrine, « Docteur ») ;
     * l'API renvoie aussi l'abréviation (honorific_short) pour les affichages courts.
     */
    public const HONORIFICS = [
        'Madame'     => 'Mme',
        'Monsieur'   => 'M.',
        'Docteur'    => 'Dr',
        'Professeur' => 'Pr',
    ];

    /** Saisies anciennes ou abrégées reconnues (sans casse, accents ni points). */
    private const HONORIFIC_ALIASES = [
        'madame' => 'Madame', 'mme' => 'Madame', 'mm' => 'Madame',
        'monsieur' => 'Monsieur', 'm' => 'Monsieur', 'mr' => 'Monsieur',
        'docteur' => 'Docteur', 'dr' => 'Docteur', 'doctor' => 'Docteur',
        'professeur' => 'Professeur', 'pr' => 'Professeur', 'prof' => 'Professeur',
    ];

    /** Photo : même disque que Expert::avatarUrl ; carré de 600 px au plus, sans agrandir. */
    public const AVATAR_DISK      = 's3';
    public const AVATAR_MAX_EDGE  = 600;
    public const AVATAR_MAX_KB    = 5120;
    public const AVATAR_MIN_EDGE  = 200;
    private const AVATAR_QUALITY  = 85;

    /** Forme complète d'un titre (« Dr » => « Docteur ») ; null si vide ou inconnu. */
    public static function honorificOf(?string $raw): ?string
    {
        $key = Str::lower(Str::ascii(str_replace('.', '', trim((string) $raw))));

        return self::HONORIFIC_ALIASES[$key] ?? null;
    }

    /** Abréviation à afficher (« Docteur » => « Dr ») ; un titre ancien hors liste est rendu tel quel. */
    public static function honorificShort(?string $honorific): ?string
    {
        $full = self::honorificOf($honorific);
        if ($full !== null) {
            return self::HONORIFICS[$full];
        }

        return ($honorific = trim((string) $honorific)) === '' ? null : $honorific;
    }

    /** Liste pour l'application : [{value: 'Docteur', short: 'Dr'}, …]. */
    public static function honorificOptions(): array
    {
        return array_map(
            fn ($full) => ['value' => $full, 'short' => self::HONORIFICS[$full]],
            array_keys(self::HONORIFICS)
        );
    }

    /**
     * @param array{honorific?:?string, name?:string, credentials?:?string, lede?:?string, palette?:?string, domain_ids?:array<int>} $input
     */
    public function update(Expert $expert, array $input): Expert
    {
        // Titre : liste fermée ; « Dr », « dr. »… sont ramenés à la forme complète.
        if (array_key_exists('honorific', $input) && trim((string) $input['honorific']) !== '') {
            $input['honorific'] = self::honorificOf($input['honorific']) ?? $input['honorific'];
        }

        $data = Validator::make($input, [
            'honorific'    => ['nullable', 'string', Rule::in(array_keys(self::HONORIFICS))],
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
            'honorific.in'  => 'Choisissez un titre dans la liste.',
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
     * Nouvelle photo : orientée selon l'EXIF, réduite à 600 px de côté au plus, enregistrée
     * sur S3 ; l'ancienne est supprimée une fois la nouvelle en place.
     */
    public function updateAvatar(Expert $expert, ?UploadedFile $file): Expert
    {
        Validator::make(['avatar' => $file], [
            'avatar' => [
                'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . self::AVATAR_MAX_KB,
                'dimensions:min_width=' . self::AVATAR_MIN_EDGE . ',min_height=' . self::AVATAR_MIN_EDGE,
            ],
        ], [
            'avatar.required'   => 'Choisissez une image.',
            'avatar.image'      => 'Le fichier doit être une image.',
            'avatar.mimes'      => 'Formats acceptés : JPG, PNG ou WebP.',
            'avatar.max'        => "L'image ne doit pas dépasser 5 Mo.",
            'avatar.dimensions' => "L'image doit faire au moins 200 × 200 pixels.",
        ])->validate();

        // intervention/image v2 : version imposée par Voyager.
        $image = (new ImageManager(['driver' => 'gd']))->make($file->getRealPath());
        $image->orientate();
        $image->resize(self::AVATAR_MAX_EDGE, self::AVATAR_MAX_EDGE, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $encoded = $image->encode('jpg', self::AVATAR_QUALITY);

        $disk = Storage::disk(self::AVATAR_DISK);
        $path = "experts/{$expert->id}/" . Str::uuid() . '.jpg';
        $disk->put($path, (string) $encoded, ['visibility' => 'public', 'ContentType' => 'image/jpeg']);

        $old = $expert->avatar;
        $expert->forceFill(['avatar' => $path])->save();

        if ($old && $old !== $path && $disk->exists($old)) {
            $disk->delete($old);
        }

        return $expert;
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