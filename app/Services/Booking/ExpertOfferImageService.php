<?php

namespace App\Services\Booking;

use App\Models\ExpertOffer;
use App\Models\ExpertOfferImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;

/**
 * Illustrations d'un thème : ajout (redimensionné), légende, ordre, suppression.
 * Utilisé par le CMS (ajout) et par l'espace expert de l'application (gestion).
 */
class ExpertOfferImageService
{
    public const MAX_IMAGES   = 6;
    public const MAX_KB       = 5120;
    public const MAX_EDGE     = 1600;
    public const CAPTION_MAX  = 150;
    public const JPEG_QUALITY = 85;

    /* ===================== AJOUT ===================== */

    /**
     * @param UploadedFile[] $files
     * @return ExpertOfferImage[]
     */
    public function add(ExpertOffer $offer, array $files, ?string $caption = null): array
    {
        Validator::make(['images' => $files], [
            'images'   => ['required', 'array', 'min:1'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:' . self::MAX_KB],
        ], [
            'images.required' => 'Choisissez au moins une image.',
            'images.*.mimes'  => 'Formats acceptés : JPG, PNG ou WebP.',
            'images.*.max'    => 'Chaque image doit faire 5 Mo au maximum.',
        ])->validate();

        $current = $offer->images()->count();
        if ($current + count($files) > self::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => 'Un thème compte au plus ' . self::MAX_IMAGES . ' images (' . $current . ' déjà enregistrée(s)).',
            ]);
        }

        $caption = $this->caption($caption);
        $order   = (int) $offer->images()->max('order_dsp');
        $created = [];

        foreach ($files as $file) {
            [$path, $width, $height] = $this->store($offer, $file);

            $created[] = ExpertOfferImage::create([
                'offer_id'  => $offer->id,
                'path'      => $path,
                // Une légende commune à un envoi groupé n'aurait pas de sens : seulement pour une image seule.
                'caption'   => count($files) === 1 ? $caption : null,
                'width'     => $width,
                'height'    => $height,
                'order_dsp' => ++$order,
            ]);
        }

        return $created;
    }

    /**
     * Redimensionne (plus grand côté ≤ 1600 px, jamais agrandi) puis enregistre sur S3.
     * PNG conservé (transparence des schémas), le reste en JPEG.
     *
     * @return array{0:string, 1:int, 2:int}
     */
    private function store(ExpertOffer $offer, UploadedFile $file): array
    {
        // intervention/image v2 : version imposée par Voyager (^2.7), déjà installée avec lui.
        $image = (new ImageManager(['driver' => 'gd']))->make($file->getRealPath());
        // Rotation selon l'EXIF (photos de téléphone), puis réduction sans jamais agrandir.
        $image->orientate();
        $image->resize(self::MAX_EDGE, self::MAX_EDGE, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        $isPng   = strtolower($file->getClientOriginalExtension()) === 'png' || $file->getMimeType() === 'image/png';
        $encoded = $isPng ? $image->encode('png') : $image->encode('jpg', self::JPEG_QUALITY);
        $path    = ExpertOfferImage::FOLDER . '/' . $offer->id . '/' . Str::uuid() . ($isPng ? '.png' : '.jpg');

        Storage::disk(ExpertOfferImage::DISK)->put($path, (string) $encoded, [
            'visibility'  => 'public',
            'ContentType' => $isPng ? 'image/png' : 'image/jpeg',
        ]);

        return [$path, $image->width(), $image->height()];
    }

    /* ===================== GESTION ===================== */

    public function updateCaption(ExpertOfferImage $image, ?string $caption): ExpertOfferImage
    {
        $image->update(['caption' => $this->caption($caption)]);

        return $image;
    }

    /**
     * @param int[] $orderedIds identifiants dans le nouvel ordre ; doivent être exactement ceux du thème
     */
    public function reorder(ExpertOffer $offer, array $orderedIds): void
    {
        $ids      = array_map('intval', $orderedIds);
        $existing = $offer->images()->pluck('id')->all();

        sort($existing);
        $check = $ids;
        sort($check);
        if ($check !== $existing) {
            throw ValidationException::withMessages(['order' => "L'ordre envoyé ne correspond pas aux images du thème."]);
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $position => $id) {
                ExpertOfferImage::where('id', $id)->update(['order_dsp' => $position + 1]);
            }
        });
    }

    public function delete(ExpertOfferImage $image): void
    {
        $path = $image->path;
        $image->delete();

        // Le fichier part après la ligne : une erreur S3 ne laisse pas d'image cassée dans l'application.
        try {
            Storage::disk(ExpertOfferImage::DISK)->delete($path);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function caption(?string $caption): ?string
    {
        $caption = trim((string) $caption);
        if ($caption === '') {
            return null;
        }
        if (mb_strlen($caption) > self::CAPTION_MAX) {
            throw ValidationException::withMessages([
                'caption' => 'La légende ne doit pas dépasser ' . self::CAPTION_MAX . ' caractères.',
            ]);
        }

        return $caption;
    }
}