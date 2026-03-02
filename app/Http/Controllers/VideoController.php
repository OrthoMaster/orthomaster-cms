<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Aws\CloudFront\CloudFrontClient;
use Illuminate\Support\Facades\Log;

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
                'region'  => 'eu-west-1',
            ]);

            // Ký cho toàn bộ folder video đó
            $cookies = $cloudFront->getSignedCookie([
                'url'         => env('CLOUDFRONT_URL') . "/{$videoCode}/*",
                'expires'     => time() + 900, // 15 minutes
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
                ->cookie('CloudFront-Expires',    $cookies['CloudFront-Expires'],    15, '/', null, true, true)
                ->cookie('CloudFront-Signature',  $cookies['CloudFront-Signature'],  15, '/', null, true, true)
                ->cookie('CloudFront-Key-Pair-Id', $cookies['CloudFront-Key-Pair-Id'], 15, '/', null, true, true);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['video']);
            return response()->json([
                'success' => false,
            ]);
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

    function parseLangs(string $text): array
    {
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
}
