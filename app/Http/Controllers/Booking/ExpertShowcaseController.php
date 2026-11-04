<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Expert;
use App\Support\Brochures\BrochurePublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CMS, onglet « Page vitrine » d'un expert (Rendez-vous > Experts) : adresse de la page,
 * publication, retrait, abandon du brouillon. Le contenu se modifie dans l'éditeur (/demo/{slug}).
 */
class ExpertShowcaseController extends Controller
{
    /**
     * PUT /booking/experts/{expertId}/showcase/slug
     * Corps : slug
     */
    public function updateSlug(Request $request, int $expertId): RedirectResponse
    {
        $expert = Expert::findOrFail($expertId);
        $previous = $expert->slug;

        BrochurePublisher::updateSlug($expert, $request->input('slug'));

        $message = $previous && $previous !== $expert->slug
            ? "Adresse modifiée : l'ancienne adresse de la page ne fonctionne plus."
            : 'Adresse de la page enregistrée.';

        return $this->back($expertId, $message);
    }

    /** POST /booking/experts/{expertId}/showcase/publish */
    public function publish(int $expertId): RedirectResponse
    {
        $expert = Expert::findOrFail($expertId);
        abort_unless($expert->slug, 422, "Définissez d'abord l'adresse de la page.");

        BrochurePublisher::publish($expert);

        return $this->back($expertId, 'Page publiée.');
    }

    /** POST /booking/experts/{expertId}/showcase/unpublish */
    public function unpublish(int $expertId): RedirectResponse
    {
        BrochurePublisher::unpublish(Expert::findOrFail($expertId));

        return $this->back($expertId, 'Page retirée : son adresse affiche désormais une page introuvable.');
    }

    /** DELETE /booking/experts/{expertId}/showcase/draft */
    public function discardDraft(int $expertId): RedirectResponse
    {
        BrochurePublisher::discardDraft(Expert::findOrFail($expertId));

        return $this->back($expertId, 'Modifications non publiées abandonnées.');
    }

    private function back(int $expertId, string $message): RedirectResponse
    {
        return redirect()
            ->route('booking.experts.show', ['expertId' => $expertId, 'tab' => 'showcase'])
            ->with(['message' => $message, 'alert-type' => 'success']);
    }
}
