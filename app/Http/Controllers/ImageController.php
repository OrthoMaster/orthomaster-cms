<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ImageController extends Controller
{
    public function uploadLessonImageToS3(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
                'id' => 'required|exists:lessons,id'
            ]);

            $file = $request->file('image');
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $originalSize = $file->getSize();

            // Process image (convert to WebP if beneficial)
            $processedImage = $this->processImage($file, $mimeType);

            // Determine final extension
            $finalExtension = $processedImage['converted'] ? 'webp' : $file->getClientOriginalExtension();

            // Create file name unique
            $fileName = 'lessons/' . $request->id . '/' . Str::uuid() . '.' . $finalExtension;

            // Upload S3
            $path = Storage::disk('s3')->put(
                $fileName,
                $processedImage['content'],
                'public'
            );

            if ($path) {
                $url = Storage::disk('s3')->url($fileName);

                // Log conversion stats
                if ($processedImage['converted']) {
                    Log::info("Image converted to WebP", [
                        'original_size' => $originalSize,
                        'new_size' => $processedImage['size'],
                        'compression' => round(($originalSize - $processedImage['size']) / $originalSize * 100, 2) . '%',
                        'mime_type' => $mimeType
                    ]);
                }

                // Return
                return response()->json([
                    'success' => true,
                    'file' => [
                        'url' => $url,
                        'name' => $originalName,
                        'path' => $fileName
                    ]
                ]);
            }

            throw new \Exception("Échec du téléchargement de l'image !");
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function uploadStudentCertificateToS3($userId, $file)
    {
        try {
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $originalSize = $file->getSize();

            // Process image (convert to WebP if beneficial)
            $processedImage = $this->processImage($file, $mimeType);

            // Determine final extension
            $finalExtension = $processedImage['converted'] ? 'webp' : $file->getClientOriginalExtension();

            // Create file name unique
            $fileName = 'student-certificates/' . $userId . '/' . Str::uuid() . '.' . $finalExtension;

            // Upload S3
            $path = Storage::disk('s3')->put(
                $fileName,
                $processedImage['content'],
                'public'
            );

            if ($path) {
                $url = Storage::disk('s3')->url($fileName);

                // Log conversion stats
                if ($processedImage['converted']) {
                    Log::info("Student certificate converted to WebP", [
                        'original_size' => $originalSize,
                        'new_size' => $processedImage['size'],
                        'compression' => round(($originalSize - $processedImage['size']) / $originalSize * 100, 2) . '%',
                        'mime_type' => $mimeType
                    ]);
                }

                // Return
                return [
                    'success' => true,
                    'file' => [
                        'url' => $url,
                        'name' => $originalName,
                        'path' => $fileName
                    ]
                ];
            }

            Log::error('Error handle student certificate.', ['register', 'user-certificate']);
            return [
                'success' => false,
                'message' => "Échec du téléchargement de certificate!"
            ];
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['register', 'user-certificate']);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }


    private function processImage($file, string $mimeType): array
    {
        $originalContent = file_get_contents($file->getPathname());
        $originalSize = strlen($originalContent);

        // Skip conversion for very small files (< 2KB)
        if ($originalSize < 2048) {
            return [
                'content' => $originalContent,
                'size' => $originalSize,
                'converted' => false,
                'reason' => 'File too small'
            ];
        }

        // Check if WebP extension is available
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            Log::warning("GD extension or WebP support not available");
            return [
                'content' => $originalContent,
                'size' => $originalSize,
                'converted' => false,
                'reason' => 'WebP not supported'
            ];
        }

        switch ($mimeType) {
            case 'image/gif':
                return $this->processGif($originalContent, $originalSize, $file->getPathname());

            case 'image/svg+xml':
                // Keep SVG as-is
                return [
                    'content' => $originalContent,
                    'size' => $originalSize,
                    'converted' => false,
                    'reason' => 'SVG kept as vector'
                ];

            case 'image/webp':
                // Already WebP
                return [
                    'content' => $originalContent,
                    'size' => $originalSize,
                    'converted' => false,
                    'reason' => 'Already WebP'
                ];

            case 'image/jpeg':
            case 'image/jpg':
            case 'image/png':
                return $this->convertToWebP($originalContent, $originalSize, $mimeType);

            default:
                return [
                    'content' => $originalContent,
                    'size' => $originalSize,
                    'converted' => false,
                    'reason' => 'Unsupported format'
                ];
        }
    }

    private function processGif(string $content, int $originalSize, string $filePath): array
    {
        // Check if GIF is animated
        if ($this->isAnimatedGif($content)) {
            Log::info("Animated GIF detected - keeping original format");
            return [
                'content' => $content,
                'size' => $originalSize,
                'converted' => false,
                'reason' => 'Animated GIF preserved'
            ];
        }

        // Static GIF - convert to WebP
        return $this->convertToWebP($content, $originalSize, 'image/gif');
    }

    private function isAnimatedGif(string $content): bool
    {
        // Simple check for animated GIF
        $imageCount = substr_count($content, "\x00\x21\xF9\x04");
        return $imageCount > 1;
    }

    private function convertToWebP(string $originalContent, int $originalSize, string $mimeType): array
    {
        try {
            // Create image resource from string
            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $image = imagecreatefromstring($originalContent);
                    break;
                case 'image/png':
                    $image = imagecreatefromstring($originalContent);
                    // Preserve transparency
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    break;
                case 'image/gif':
                    $image = imagecreatefromstring($originalContent);
                    break;
                default:
                    throw new \Exception("Unsupported image type for conversion");
            }

            if (!$image) {
                throw new \Exception("Failed to create image resource");
            }

            // Get image dimensions
            $width = imagesx($image);
            $height = imagesy($image);

            // Skip conversion for very large images (memory concern)
            if ($width * $height > 4000 * 4000) {
                imagedestroy($image);
                return [
                    'content' => $originalContent,
                    'size' => $originalSize,
                    'converted' => false,
                    'reason' => 'Image too large for conversion'
                ];
            }

            // Convert to WebP with quality based on original format
            $quality = $this->getWebPQuality($mimeType, $originalSize);

            ob_start();
            imagewebp($image, null, $quality);
            $webpContent = ob_get_clean();
            imagedestroy($image);

            $webpSize = strlen($webpContent);

            // Only use WebP if it's significantly smaller (at least 10% reduction)
            if ($webpSize < $originalSize * 0.9) {
                Log::info("WebP conversion successful", [
                    'original_size' => $originalSize,
                    'webp_size' => $webpSize,
                    'savings' => round(($originalSize - $webpSize) / $originalSize * 100, 2) . '%'
                ]);

                return [
                    'content' => $webpContent,
                    'size' => $webpSize,
                    'converted' => true,
                    'reason' => 'Converted to WebP'
                ];
            } else {
                Log::info("WebP conversion not beneficial", [
                    'original_size' => $originalSize,
                    'webp_size' => $webpSize
                ]);

                return [
                    'content' => $originalContent,
                    'size' => $originalSize,
                    'converted' => false,
                    'reason' => 'WebP not smaller'
                ];
            }
        } catch (\Exception $e) {
            Log::error("WebP conversion failed", ['error' => $e->getMessage()]);

            return [
                'content' => $originalContent,
                'size' => $originalSize,
                'converted' => false,
                'reason' => 'Conversion failed: ' . $e->getMessage()
            ];
        }
    }

    private function getWebPQuality(string $mimeType, int $fileSize): int
    {
        // Adaptive quality based on original format and file size
        switch ($mimeType) {
            case 'image/png':
                // PNG usually has high quality, use higher WebP quality
                return $fileSize > 500000 ? 85 : 90; // 500KB threshold

            case 'image/jpeg':
            case 'image/jpg':
                // JPEG already compressed, moderate WebP quality
                return $fileSize > 500000 ? 80 : 85;

            case 'image/gif':
                // GIF has limited colors, higher quality WebP
                return 90;

            default:
                return 85;
        }
    }
}
