<?php

namespace App\Http\Controllers\FairPlay;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FairPlayController extends Controller
{
    /**
     * Return certificate url from S3
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCertificate(Request $request)
    {
        try {
            // Link certificate from storage
            $path = setting('system.fairplay_cert_path');
            if (!$path) {
                throw new \Exception('Parameter not found');
            }
            $certPath = storage_path($path);
            
            // Cache certificate 1 hr
            $certificate = Cache::remember('fairplay_certificate', 3600, function () use ($certPath) {
                return file_get_contents($certPath);
            });

            if (empty($certificate)) {
                return response()->json([
                    'error' => 'Failed to read certificate'
                ], 500);
            }

            return response($certificate)
                ->header('Content-Type', 'application/x-x509-ca-cert')
                ->header('Content-Disposition', 'inline; filename="fairplay.cer"')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type')
                ->header('Cache-Control', 'public, max-age=3600');
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear cache certificate
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache(Request $request)
    {
        Cache::forget('fairplay_certificate');

        return response()->json([
            'success' => true,
            'message' => 'Certificate cache cleared successfully'
        ]);
    }
}
