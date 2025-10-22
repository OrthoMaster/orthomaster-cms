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
    protected $fairplayService;

    public function __construct(FairPlayService $fairplayService)
    {
        $this->fairplayService = $fairplayService;
    }

    /**
     * Return certificate url from S3
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCertificate(Request $request)
    {
        try {
            Log::info('FairPlay: Certificate request', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Validate configuration
            $this->fairplayService->validateConfiguration();

            // Get certificate (cached for 1 hour)
            $certificate = Cache::remember('fairplay_certificate', 3600, function () {
                return $this->fairplayService->getCertificate();
            });

            if (empty($certificate)) {
                return response()->json([
                    'error' => 'Failed to read certificate'
                ], 500);
            }

            Log::info('FairPlay: Certificate sent', [
                'size' => strlen($certificate),
            ]);

            return response($certificate)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Length', strlen($certificate))
                ->header('Content-Disposition', 'inline; filename="fairplay.cer"')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Range')
                ->header('Access-Control-Expose-Headers', 'Content-Length, Content-Range')
                ->header('Cache-Control', 'public, max-age=3600');
        } catch (\Exception $e) {
            Log::error('FairPlay: Certificate error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Handle OPTIONS preflight for certificate
     */
    public function certificateOptions()
    {
        return response('', 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Range')
            ->header('Access-Control-Max-Age', '3600');
    }

    /**
     * Get FairPlay license (CKC from SPC)
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getLicense(Request $request)
    {
        try {
            // Get SPC from request body
            $spcData = $request->getContent();

            Log::info('FairPlay: License request', [
                'ip' => $request->ip(),
                'content_type' => $request->header('Content-Type'),
                'content_length' => strlen($spcData),
                'user_agent' => $request->userAgent(),
            ]);

            if (empty($spcData)) {
                Log::warning('FairPlay: Empty SPC received');
                return response()->json(['error' => 'Empty SPC data'], 400)
                    ->header('Access-Control-Allow-Origin', '*');
            }

            // Check if SPC is base64 encoded
            $isBase64 = $this->isBase64($spcData);
            if (!$isBase64) {
                // If raw binary, encode to base64
                $spcData = base64_encode($spcData);
                Log::info('FairPlay: Converted raw SPC to base64');
            }

            // Get asset ID from query parameter or header
            $assetId = $request->query('asset_id')
                ?? $request->header('X-Asset-ID')
                ?? $request->header('X-Content-ID');

            Log::info('FairPlay: Processing license request', [
                'asset_id' => $assetId,
                'spc_length' => strlen($spcData),
            ]);

            // Generate CKC
            $ckcData = $this->fairplayService->generateCKC($spcData, $assetId);

            if (empty($ckcData)) {
                throw new \Exception('Failed to generate CKC');
            }

            // Decode CKC from base64 to binary
            $ckcBinary = base64_decode($ckcData);

            Log::info('FairPlay: License generated successfully', [
                'asset_id' => $assetId,
                'ckc_length' => strlen($ckcBinary),
            ]);

            return response($ckcBinary, 200)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Length', strlen($ckcBinary))
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Asset-ID, X-Content-ID')
                ->header('Access-Control-Expose-Headers', 'Content-Length');
        } catch (\Exception $e) {
            Log::error('FairPlay: License generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'License generation failed',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500)
                ->header('Access-Control-Allow-Origin', '*');
        }
    }

    /**
     * Handle OPTIONS preflight for license
     */
    public function licenseOptions()
    {
        return response('', 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Asset-ID, X-Content-ID')
            ->header('Access-Control-Max-Age', '3600');
    }

    /**
     * Test endpoint to validate FairPlay configuration
     */
    public function testConfiguration()
    {
        try {
            $this->fairplayService->validateConfiguration();

            $certificate = $this->fairplayService->getCertificate();

            return response()->json([
                'status' => 'ok',
                'message' => 'FairPlay configuration is valid',
                'details' => [
                    'certificate_size' => strlen($certificate),
                    'ask_configured' => !empty(config('services.fairplay.ask')),
                    'ask_length' => strlen(config('services.fairplay.ask')),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if string is base64 encoded
     */
    private function isBase64($string)
    {
        if (!is_string($string)) {
            return false;
        }

        // Check if it contains only valid base64 characters
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string)) {
            return false;
        }

        // Try to decode and re-encode
        $decoded = base64_decode($string, true);
        if ($decoded === false) {
            return false;
        }

        return base64_encode($decoded) === $string;
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
