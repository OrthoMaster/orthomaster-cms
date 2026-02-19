<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Aws\CloudFront\CloudFrontClient;

class VideoController extends Controller
{
    public function getSignedCookies(Request $request, $videoId)
    {
        $user = $request->user();

        $cloudFront = new CloudFrontClient([
            'version' => 'latest',
            'region'  => 'eu-west-1',
        ]);

        // Ký cho toàn bộ folder video đó
        $cookies = $cloudFront->getSignedCookie([
            'url'         => env('CLOUDFRONT_URL') . "/{$videoId}/*",
            'expires'     => time() + 900, // 15 minutes
            'private_key' => file_get_contents(env('CLOUDFRONT_PRIVATE_KEY_PATH')),
            'key_pair_id' => env('CLOUDFRONT_KEY_PAIR_ID'),
        ]);

        $videoUrl = env('CLOUDFRONT_URL') . "/{$videoId}/master.m3u8";

        return response()->json(['url' => $videoUrl])
            ->cookie('CloudFront-Policy', $cookies['CloudFront-Policy'], 15, '/', null, true, true)
            ->cookie('CloudFront-Signature', $cookies['CloudFront-Signature'], 15, '/', null, true, true)
            ->cookie('CloudFront-Key-Pair-Id', $cookies['CloudFront-Key-Pair-Id'], 15, '/', null, true, true);
    }
}
