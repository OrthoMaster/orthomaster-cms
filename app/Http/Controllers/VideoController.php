<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Aws\CloudFront\CloudFrontClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    public function getSignedCookies(Request $request, $videoCode)
    {
        $video = Video::findByCode($videoCode);
        if (!$video) {
            return response()->json([
                'success' => false,
            ]);
        }

        try {
            $cloudFront = new CloudFrontClient([
                'version' => 'latest',
                'region'  => 'us-east-1',
            ]);

            $resourceKey = env('CLOUDFRONT_URL') . "/{$videoCode}/*";
            $expires = time() + 900;

            $customPolicy = json_encode([
                'Statement' => [
                    [
                        'Resource' => $resourceKey,
                        'Condition' => [
                            'DateLessThan' => [
                                'AWS:EpochTime' => $expires
                            ]
                        ]
                    ]
                ]
            ]);

            $cookies = $cloudFront->getSignedCookie([
                'policy' => $customPolicy,
                'private_key' => file_get_contents(storage_path('app/cloudfront-private-key.pem')),
                'key_pair_id' => env('CLOUDFRONT_KEY_PAIR_ID'),
            ]);

            $videoUrl = env('CLOUDFRONT_URL') . "/{$videoCode}/master.m3u8";

            return response()->json([
                'success' => true,
                'video' => $video,
                'url' => $videoUrl,
                'highlights' => $this->parseVideoHighlights($video->highlights),
                'subtitles' => $this->parseLangs($video->subtitles),
            ])
                ->header('Access-Control-Allow-Credentials', 'true')
                ->cookie('CloudFront-Policy',     $cookies['CloudFront-Policy'],     15, '/', '.orthomaster.fr', true, false, false, 'None')
                ->cookie('CloudFront-Signature',  $cookies['CloudFront-Signature'],  15, '/', '.orthomaster.fr', true, false, false, 'None')
                ->cookie('CloudFront-Key-Pair-Id', $cookies['CloudFront-Key-Pair-Id'], 15, '/', '.orthomaster.fr', true, false, false, 'None');
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['video']);
            return response()->json(['success' => false]);
        }
    }

    /**
     * Mobile endpoint - Returns proxy URL
     */
    public function getProxyUrl(Request $request, $videoCode)
    {
        $video = Video::findByCode($videoCode);
        if (!$video) {
            return response()->json(['success' => false]);
        }

        try {
            // Return Laravel proxy URL instead of direct S3 URL
            $proxyUrl = route('video.mobile-manifest', ['videoCode' => $videoCode]);

            // Subtitle URLs for UI display
            $rootFolder = 'hsl-videos';
            $subtitleLangs = $this->parseLangs($video->subtitles);
            $subtitles = [];

            foreach ($subtitleLangs as $lang) {
                $subtitles[] = [
                    'language' => $lang,
                    'label' => $this->getLangLabel($lang),
                    'url' => Storage::disk('s3')->url($rootFolder . "/{$videoCode}/subtitles/{$lang}/{$lang}.vtt"),
                ];
            }

            return response()->json([
                'success' => true,
                'video' => $video,
                'url' => $proxyUrl,
                'highlights' => $this->parseVideoHighlights($video->highlights),
                'subtitles' => $subtitles,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['video']);
            return response()->json(['success' => false]);
        }
    }

    /**
     * Proxy master.m3u8 and convert relative paths to absolute URLs
     */
    public function getMobileManifest($videoCode)
    {
        $video = Video::findByCode($videoCode);
        if (!$video) {
            abort(404, 'Video not found');
        }

        try {
            $rootFolder = 'hsl-videos';

            // Fetch original master.m3u8 from S3
            $masterPath = $rootFolder . "/{$videoCode}/master.m3u8";

            if (!Storage::disk('s3')->exists($masterPath)) {
                Log::error('Master playlist not found', ['path' => $masterPath]);
                abort(404, 'Master playlist not found');
            }

            $content = Storage::disk('s3')->get($masterPath);

            // Base URL for this video (keep videoCode in path!)
            $baseUrl = Storage::disk('s3')->url($rootFolder . "/{$videoCode}");

            // Convert relative paths to absolute URLs
            $lines = explode("\n", $content);
            $modified = [];
            $isFirstSubtitle = true;  // 👈 Track first subtitle

            foreach ($lines as $line) {
                $trimmed = trim($line);

                // Convert URIs in #EXT-X-MEDIA tags (subtitles)
                if (preg_match('/URI="([^"]+)"/', $line, $matches)) {
                    $relativePath = $matches[1];

                    // Only convert if not already absolute
                    if (!preg_match('/^https?:\/\//', $relativePath)) {
                        $absoluteUrl = $baseUrl . '/' . ltrim($relativePath, '/');
                        $line = str_replace('URI="' . $relativePath . '"', 'URI="' . $absoluteUrl . '"', $line);

                        // 👇 Set DEFAULT and AUTOSELECT for first subtitle only
                        if ($isFirstSubtitle && strpos($line, 'TYPE=SUBTITLES') !== false) {
                            // Set first subtitle as DEFAULT=YES and AUTOSELECT=YES
                            $line = preg_replace('/DEFAULT=(YES|NO)/', 'DEFAULT=YES', $line);
                            $line = preg_replace('/AUTOSELECT=(YES|NO)/', 'AUTOSELECT=YES', $line);

                            // If DEFAULT doesn't exist, add it
                            if (strpos($line, 'DEFAULT=') === false) {
                                $line = str_replace('TYPE=SUBTITLES', 'TYPE=SUBTITLES,DEFAULT=YES', $line);
                            }

                            // If AUTOSELECT doesn't exist, add it
                            if (strpos($line, 'AUTOSELECT=') === false) {
                                $line = str_replace('TYPE=SUBTITLES', 'TYPE=SUBTITLES,AUTOSELECT=YES', $line);
                            }

                            $isFirstSubtitle = false;
                        } else if (strpos($line, 'TYPE=SUBTITLES') !== false) {
                            // Set other subtitles as DEFAULT=NO and AUTOSELECT=NO
                            $line = preg_replace('/DEFAULT=(YES|NO)/', 'DEFAULT=NO', $line);
                            $line = preg_replace('/AUTOSELECT=(YES|NO)/', 'AUTOSELECT=NO', $line);

                            // If DEFAULT doesn't exist, add it
                            if (strpos($line, 'DEFAULT=') === false) {
                                $line = str_replace('TYPE=SUBTITLES', 'TYPE=SUBTITLES,DEFAULT=NO', $line);
                            }

                            // If AUTOSELECT doesn't exist, add it
                            if (strpos($line, 'AUTOSELECT=') === false) {
                                $line = str_replace('TYPE=SUBTITLES', 'TYPE=SUBTITLES,AUTOSELECT=NO', $line);
                            }
                        }
                    }
                }

                // Convert stream playlist paths (lines without #)
                if (!empty($trimmed) && !str_starts_with($trimmed, '#')) {
                    if (!preg_match('/^https?:\/\//', $trimmed)) {
                        $absolutePath = $baseUrl . '/' . ltrim($trimmed, '/');
                        $line = $absolutePath;
                    }
                }

                $modified[] = $line;
            }

            $modifiedContent = implode("\n", $modified);

            return response($modifiedContent, 200)
                ->header('Content-Type', 'application/vnd.apple.mpegurl')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        } catch (\Exception $e) {
            Log::channel('api')->error('Mobile manifest error', [
                'videoCode' => $videoCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            abort(500, 'Failed to generate manifest: ' . $e->getMessage());
        }
    }

    function parseVideoHighlights(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $result = [];
        $id = 1;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Match m:ss or mm:ss or h:mm:ss
            if (preg_match('/^(\d+):(\d+)(?::(\d+))?\s+(.+)$/', $line, $matches)) {
                if (isset($matches[3]) && $matches[3] !== '') {
                    // h:mm:ss
                    $seconds = (int)$matches[1] * 3600 + (int)$matches[2] * 60 + (int)$matches[3];
                    $label = $matches[4];
                } else {
                    // m:ss
                    $seconds = (int)$matches[1] * 60 + (int)$matches[2];
                    $label = $matches[4];
                }

                $result[] = [
                    'id'    => $id++,
                    'time'  => $seconds,
                    'label' => trim($label),
                ];
            }
        }

        return $result;
    }

    function parseLangs($text)
    {
        if (!$text) {
            return [];
        }

        // Split by ; or ,
        $langs = preg_split('/[;,]/', $text);

        $result = [];
        foreach ($langs as $lang) {
            $lang = trim($lang);
            if (!empty($lang)) {
                $result[] = $lang;
            }
        }

        // Remove duplicates
        return array_values(array_unique($result));
    }

    private function getLangLabel($lang)
    {
        $labels = [
            'fr' => 'Français',
            'en' => 'English',
            'de' => 'Deutsch',
        ];
        return $labels[$lang] ?? strtoupper($lang);
    }
}
