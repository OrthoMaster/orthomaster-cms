<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoNotificationService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';
    private const EXPO_RECEIPT_URL = 'https://exp.host/--/api/v2/push/getReceipts';
    private const MAX_BATCH_SIZE = 100; // Expo limit

    /**
     * Gửi notification đến 1 device
     */
    public function sendToDevice(
        string $expoPushToken,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ): array {
        return $this->sendToMultipleDevices([$expoPushToken], $title, $body, $data, $options);
    }

    /**
     * Gửi notification đến nhiều devices
     */
    public function sendToMultipleDevices(
        array $expoPushTokens,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ): array {
        // Validate tokens
        $validTokens = $this->validateTokens($expoPushTokens);
        
        if (empty($validTokens)) {
            return [
                'success' => false,
                'error' => 'No valid Expo Push Tokens provided',
            ];
        }

        // Build messages
        $messages = $this->buildMessages($validTokens, $title, $body, $data, $options);

        // Send in batches
        $results = [];
        $chunks = array_chunk($messages, self::MAX_BATCH_SIZE);

        foreach ($chunks as $chunk) {
            $response = $this->sendBatch($chunk);
            $results[] = $response;
        }

        return [
            'success' => true,
            'results' => $results,
        ];
    }

    /**
     * Gửi notification đến user (tất cả devices của user)
     */
    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ): array {
        $devices = \App\Models\DeviceToken::where('user_id', $userId)
            ->active()
            ->pluck('fcm_token')
            ->toArray();

        if (empty($devices)) {
            return [
                'success' => false,
                'error' => 'User has no active devices',
            ];
        }

        return $this->sendToMultipleDevices($devices, $title, $body, $data, $options);
    }

    /**
     * Gửi notification đến nhiều users
     */
    public function sendToUsers(
        array $userIds,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ): array {
        $devices = \App\Models\DeviceToken::whereIn('user_id', $userIds)
            ->active()
            ->pluck('fcm_token')
            ->toArray();

        if (empty($devices)) {
            return [
                'success' => false,
                'error' => 'No active devices found for these users',
            ];
        }

        return $this->sendToMultipleDevices($devices, $title, $body, $data, $options);
    }

    /**
     * Validate Expo Push Tokens
     */
    private function validateTokens(array $tokens): array
    {
        return array_filter($tokens, function ($token) {
            return is_string($token) && 
                   (str_starts_with($token, 'ExponentPushToken[') || 
                    str_starts_with($token, 'ExpoPushToken['));
        });
    }

    /**
     * Build notification messages
     */
    private function buildMessages(
        array $tokens,
        string $title,
        string $body,
        array $data,
        array $options
    ): array {
        $messages = [];

        foreach ($tokens as $token) {
            $message = [
                'to' => $token,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'sound' => $options['sound'] ?? 'default',
                'priority' => $options['priority'] ?? 'high',
                'channelId' => $options['channelId'] ?? 'default',
            ];

            // Optional: badge
            if (isset($options['badge'])) {
                $message['badge'] = $options['badge'];
            }

            // Optional: TTL (time to live)
            if (isset($options['ttl'])) {
                $message['ttl'] = $options['ttl'];
            }

            // Optional: subtitle (iOS only)
            if (isset($options['subtitle'])) {
                $message['subtitle'] = $options['subtitle'];
            }

            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * Send batch to Expo
     */
    private function sendBatch(array $messages): array
    {
        try {
            $response = Http::timeout(30)
                ->post(self::EXPO_PUSH_URL, $messages);

            if ($response->successful()) {
                $result = $response->json();
                
                // Log errors nếu có
                $this->handleTicketErrors($result['data'] ?? []);

                return [
                    'success' => true,
                    'data' => $result['data'] ?? [],
                ];
            }

            Log::error('Expo Push Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Expo Push Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle ticket errors và deactivate invalid tokens
     */
    private function handleTicketErrors(array $tickets): void
    {
        foreach ($tickets as $ticket) {
            if (isset($ticket['status']) && $ticket['status'] === 'error') {
                $errorCode = $ticket['details']['error'] ?? null;

                // Deactivate device nếu token không hợp lệ
                if ($errorCode === 'DeviceNotRegistered') {
                    Log::warning('Device not registered', $ticket);
                    // TODO: Deactivate device in database
                }

                Log::error('Expo Ticket Error', $ticket);
            }
        }
    }

    /**
     * Get receipts để kiểm tra delivery status
     */
    public function getReceipts(array $ticketIds): array
    {
        try {
            $response = Http::post(self::EXPO_RECEIPT_URL, [
                'ids' => $ticketIds,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'success' => false,
                'error' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Get Receipts Error', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}