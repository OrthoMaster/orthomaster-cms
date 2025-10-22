<?php

namespace App\Http\Controllers\FairPlay;

use Illuminate\Support\Facades\Log;

class FairPlayService
{
    private $ask;
    private $certificatePath;
    private $privateKeyPath;
    private $privateKeyPassword;
    private $certificationPath;
    private $provisioningPath;

    public function __construct()
    {
        $this->ask = config('services.fairplay.ask');
        $this->certificatePath = storage_path(config('services.fairplay.certificate_path'));
        $this->privateKeyPath = storage_path(config('services.fairplay.private_key_path'));
        $this->privateKeyPassword = config('services.fairplay.private_key_password');
        $this->certificationPath = storage_path(config('services.fairplay.certification_path'));
        $this->provisioningPath = storage_path(config('services.fairplay.provisioning_path'));
    }

    public function getCertificate()
    {
        return file_get_contents($this->certificatePath);
    }

    /**
     * Generate CKC from SPC using FairPlay protocol
     */
    public function generateCKC(string $spcBinary, ?string $contentId = null): string
    {
        // 1) Validate input
        if ($spcBinary === '' || strlen($spcBinary) > 65536) {
            throw new \InvalidArgumentException('Invalid SPC size');
        }

        // 2) Lấy content key theo contentId (hoặc KID)
        $contentKey = $this->getContentKey($contentId);
        if (!$contentKey) {
            throw new \RuntimeException('Content key not found for contentId');
        }

        // 3) Gọi KSM/FairPlay server SDK để tạo CKC từ SPC + contentKey.
        //    (KHÔNG tự parse SPC/CKC.)
        $ckcBinary = $this->ksm->createCKC($spcBinary, $contentKey, [
            'content_id' => $contentId,
            // ... các tham số Apple yêu cầu (nonce, iv policy, rental, v.v.)
        ]);

        if (!$ckcBinary || strlen($ckcBinary) === 0) {
            throw new \RuntimeException('KSM failed to generate CKC');
        }

        return $ckcBinary; // nhị phân
    }


    /**
     * Parse SPC binary structure
     */
    private function parseSPC($spcBinary)
    {
        // SPC structure (simplified):
        // - Version (4 bytes)
        // - Session data (variable)

        $info = [];

        if (strlen($spcBinary) < 4) {
            throw new \Exception('Invalid SPC: too short');
        }

        // Extract version
        $info['version'] = unpack('N', substr($spcBinary, 0, 4))[1];

        // Extract session data
        $info['session_data'] = substr($spcBinary, 4);

        Log::info('FairPlay: SPC parsed', [
            'version' => $info['version'],
            'session_length' => strlen($info['session_data'])
        ]);

        return $info;
    }

    /**
     * Get content key - MUST match key used to encrypt video
     */
    private function getContentKey($contentId)
    {
        // CRITICAL: This key MUST match the key used when encrypting video
        // In your case, when you encrypted HLS with FFmpeg/Shaka Packager

        // For now, use a fixed key for all content
        // In production, store per-content keys in database

        $fixedKey = env('FAIRPLAY_CONTENT_KEY', '0123456789abcdef0123456789abcdef');

        return hex2bin($fixedKey);
    }

    /**
     * Build CKC payload according to FairPlay spec
     */
    private function buildCKCPayload($data)
    {
        $payload = '';

        // 1. Content Key (16 bytes for AES-128)
        $payload .= $data['content_key'];

        // 2. Key ID (16 bytes - derived from content ID)
        $keyId = $this->generateKeyId($data['content_id']);
        $payload .= $keyId;

        // 3. Lease duration (4 bytes, big-endian)
        // 0 = unlimited, or specify seconds
        $leaseDuration = 0;
        $payload .= pack('N', $leaseDuration);

        // 4. Optional: Add certification data for validation
        if (file_exists($this->certificationPath)) {
            $certification = file_get_contents($this->certificationPath);
            $payload .= $certification;
        }

        Log::info('FairPlay: CKC payload built', [
            'payload_length' => strlen($payload),
            'content_key_length' => strlen($data['content_key']),
            'key_id_length' => strlen($keyId)
        ]);

        return $payload;
    }

    /**
     * Sign CKC with ASK using HMAC-SHA256
     */
    private function signCKC($ckcPayload)
    {
        // Convert ASK from hex to binary
        $askBinary = hex2bin($this->ask);

        if (strlen($askBinary) !== 16) {
            throw new \Exception('Invalid ASK length');
        }

        // Create HMAC signature
        $signature = hash_hmac('sha256', $ckcPayload, $askBinary, true);

        // Append signature to payload
        $signedCKC = $ckcPayload . $signature;

        Log::info('FairPlay: CKC signed', [
            'signature_length' => strlen($signature),
            'total_length' => strlen($signedCKC)
        ]);

        return $signedCKC;
    }

    /**
     * Generate Key ID from content ID
     */
    private function generateKeyId($contentId)
    {
        if (empty($contentId)) {
            $contentId = 'default';
        }

        // Key ID must be 16 bytes
        return md5($contentId, true);
    }

    private function isBase64($string)
    {
        if (!is_string($string)) return false;
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string)) return false;

        $decoded = base64_decode($string, true);
        if ($decoded === false) return false;

        return base64_encode($decoded) === $string;
    }

    public function validateConfiguration()
    {
        $errors = [];

        if (empty($this->ask) || strlen($this->ask) !== 32) {
            $errors[] = 'ASK invalid';
        }

        if (!file_exists($this->certificatePath)) {
            $errors[] = 'Certificate not found';
        }

        if (!file_exists($this->privateKeyPath)) {
            $errors[] = 'Private key not found';
        }

        if (empty($this->privateKeyPassword)) {
            $errors[] = 'Private key password not set';
        }

        // Test private key
        $key = @openssl_pkey_get_private(
            file_get_contents($this->privateKeyPath),
            $this->privateKeyPassword
        );

        if (!$key) {
            $errors[] = 'Cannot load private key - wrong password?';
        }

        if (!empty($errors)) {
            throw new \Exception('FairPlay config errors: ' . implode(', ', $errors));
        }

        return true;
    }
}
