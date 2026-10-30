<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\ExpertOffer;
use App\Models\ExpertOfferImage;
use App\Services\Booking\ExpertOfferImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CMS : illustrations d'un thème. L'ajout se fait ici
 * (ainsi que depuis la version web de l'application) ; l'expert gère ensuite légendes,
 * ordre et suppression dans l'application.
 */
class OfferImageController extends Controller
{
    public function __construct(private ExpertOfferImageService $images)
    {
    }

    /**
     * GET /booking/experts/{expertId}/offers/{offer}/images
     */
    public function index(int $expertId, ExpertOffer $offer)
    {
        $expert = $this->expert($expertId, $offer);
        $images = $offer->images()->get();

        return view('booking.experts.offer-images', [
            'expert'     => $expert,
            'offer'      => $offer,
            'images'     => $images,
            'maxImages'  => ExpertOfferImageService::MAX_IMAGES,
            'captionMax' => ExpertOfferImageService::CAPTION_MAX,
        ]);
    }

    /**
     * POST /booking/experts/{expertId}/offers/{offer}/images
     * images[], caption
     */
    public function store(Request $request, int $expertId, ExpertOffer $offer): RedirectResponse
    {
        $this->expert($expertId, $offer);
        $created = $this->images->add($offer, (array) $request->file('images', []), $request->input('caption'));

        return $this->back($expertId, $offer, count($created) > 1 ? count($created) . ' images ajoutées.' : 'Image ajoutée.');
    }

    /**
     * PUT /booking/experts/{expertId}/offers/{offer}/images/{image}
     * caption
     */
    public function update(Request $request, int $expertId, ExpertOffer $offer, ExpertOfferImage $image): RedirectResponse
    {
        $this->expert($expertId, $offer, $image);
        $this->images->updateCaption($image, $request->input('caption'));

        return $this->back($expertId, $offer, 'Légende enregistrée.');
    }

    /**
     * POST /booking/experts/{expertId}/offers/{offer}/images/{image}/move
     * direction : up | down
     */
    public function move(Request $request, int $expertId, ExpertOffer $offer, ExpertOfferImage $image): RedirectResponse
    {
        $this->expert($expertId, $offer, $image);

        $ids   = $offer->images()->pluck('id')->all();
        $index = array_search($image->id, $ids, true);
        $swap  = $request->input('direction') === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($ids[$swap])) {
            [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
            $this->images->reorder($offer, $ids);
        }

        return $this->back($expertId, $offer, 'Ordre mis à jour.');
    }

    /**
     * DELETE /booking/experts/{expertId}/offers/{offer}/images/{image}
     */
    public function destroy(int $expertId, ExpertOffer $offer, ExpertOfferImage $image): RedirectResponse
    {
        $this->expert($expertId, $offer, $image);
        $this->images->delete($image);

        return $this->back($expertId, $offer, 'Image supprimée.');
    }

    /* ===================== OUTILS ===================== */

    /** Vérifie la chaîne expert → thème → image : une URL modifiée renvoie 404. */
    private function expert(int $expertId, ExpertOffer $offer, ?ExpertOfferImage $image = null): object
    {
        $expert = DB::table('experts')->where('id', $expertId)->first(['id', 'name']);
        abort_unless($expert && $offer->expert_id === $expertId, 404);
        abort_if($image && $image->offer_id !== $offer->id, 404);

        return $expert;
    }

    private function back(int $expertId, ExpertOffer $offer, string $message): RedirectResponse
    {
        return redirect()
            ->route('booking.experts.offers.images.index', [$expertId, $offer->id])
            ->with(['message' => $message, 'alert-type' => 'success']);
    }
}