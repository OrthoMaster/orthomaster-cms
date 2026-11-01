<?php

namespace App\Support\Brochures;

use App\Http\Controllers\BrochureController;
use App\Models\Expert;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * Page vitrine d'un expert : publication et état, partagés par l'éditeur (/demo/{slug})
 * et l'onglet « Page vitrine » du CMS (Rendez-vous > Experts).
 */
class BrochurePublisher
{
    /** Chemins réservés du sous-domaine : un slug ne peut pas les prendre. */
    public const RESERVED_SLUGS = ['demo', 'experts', 'admin', 'api', 'login', 'register'];
    public const SLUG_MAX = 80;

    /**
     * Copie le brouillon dans experts (model + brochure) et met la page en ligne.
     * Sans brouillon, une page retirée qui a déjà un contenu publié est simplement remise en ligne.
     * 422 s'il n'y a rien à publier.
     */
    public static function publish(Expert $expert): Carbon
    {
        $now = now();

        DB::transaction(function () use ($expert, $now) {
            $draft = $expert->brochureDraft()->lockForUpdate()->first();
            if (!$draft) {
                abort_unless($expert->brochure, 422, 'Aucune modification à publier.');
                $expert->forceFill(['brochure_published_at' => $now])->save();
                return;
            }

            $expert->forceFill([
                ...$draft->only(BrochureDraft::COLUMNS),
                'brochure'              => $draft->data,
                'brochure_published_at' => $now,
            ])->save();

            $draft->timestamps = false; // conserver la date du dernier enregistrement
            $draft->forceFill(['published_at' => $now])->save();
        });

        return $now;
    }

    /** Retire la page publique (404) ; le contenu publié et le brouillon sont conservés. */
    public static function unpublish(Expert $expert): void
    {
        $expert->forceFill(['brochure_published_at' => null])->save();
    }

    /** Abandonne les modifications non publiées : l'éditeur repart de la version publiée. */
    public static function discardDraft(Expert $expert): void
    {
        $expert->brochureDraft()->delete();
    }

    /** Valide puis enregistre l'adresse de la page (slug). */
    public static function updateSlug(Expert $expert, ?string $slug): void
    {
        $slug = Str::lower(trim((string) $slug));

        Validator::make(['slug' => $slug], [
            'slug' => [
                'required', 'string', 'max:' . self::SLUG_MAX,
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(self::RESERVED_SLUGS),
                Rule::unique('experts', 'slug')->ignore($expert->id),
            ],
        ], [
            'slug.required' => "Saisissez l'adresse de la page.",
            'slug.regex'    => 'Lettres minuscules sans accents, chiffres et tirets seulement (ex. claire-martin).',
            'slug.not_in'   => 'Cette adresse est réservée.',
            'slug.unique'   => 'Cette adresse est déjà utilisée par un autre expert.',
        ], ['slug' => 'adresse'])->validate();

        $expert->forceFill(['slug' => $slug])->save();
    }

    /**
     * Tout ce que l'onglet « Page vitrine » affiche : état, liens, contenu.
     *
     * @return array<string, mixed>
     */
    public static function overview(Expert $expert): array
    {
        $draft = $expert->brochureDraft;
        $published = $expert->brochure_published_at;

        $pending = $draft && !$draft->isPublished();
        // new : rien du tout ; draft : brouillon jamais publié ; published : en ligne (pending :
        // en ligne avec des modifications à publier) ; unpublished : retirée après publication.
        $state = $published
            ? ($pending ? 'pending' : 'published')
            : ($expert->brochure ? 'unpublished' : ($draft ? 'draft' : 'new'));

        // Contenu affiché : brouillon s'il existe, sinon la version publiée, sinon rien.
        $source  = $draft ?? ($expert->brochure ? $expert : null);
        $content = $source ? BrochureDraft::fromRecord($source, $draft ? 'data' : 'brochure', $expert) : null;

        return [
            'slug'           => $expert->slug,
            'suggested_slug' => Str::slug((string) $expert->name),
            'state'          => $state,
            'published_at'   => $published,
            'draft_saved_at' => $draft?->updated_at,
            'has_draft'      => $draft !== null,
            'draft_pending'  => $pending,
            'can_publish'    => $pending || (!$published && ($draft || $expert->brochure)),
            'public_url'     => $expert->slug ? BrochureController::publicUrl($expert) : null,
            'editor_url'     => $expert->slug ? BrochureController::editorUrl($expert) : null,
            // Éditeur ouvert par l'administrateur connecté au CMS (session du domaine cms.*).
            'admin_editor_url' => $expert->slug ? route('brochures.demo', $expert) : null,
            'content'        => $content,
            'palettes'       => BrochureDraft::PALETTES,
            'models'         => BrochureDraft::MODELS,
        ];
    }
}
