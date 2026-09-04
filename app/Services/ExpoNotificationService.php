<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoNotificationService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';
    private const EXPO_RECEIPT_URL = 'https://exp.host/--/api/v2/push/getReceipts';
    private const MAX_BATCH_SIZE = 100; // Expo limit

    /** Erreur Expo signalant un jeton devenu inutilisable */
    private const ERROR_UNREGISTERED = 'DeviceNotRegistered';

    /**
     * Send notification to 1 device
     */
    public function sendToDevice(
        string $expoPushToken,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ) {
        return $this->sendToMultipleDevices([$expoPushToken], $title, $body, $data, $options);
    }

    /**
     * Send notification to many devices
     */
    public function sendToMultipleDevices(
        array $expoPushTokens,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ) {
        // Validate tokens
        $validTokens = array_values($this->validateTokens($expoPushTokens));

        if (empty($validTokens)) {
            return false;
        }

        // Build messages
        $messages = $this->buildMessages($validTokens, $title, $body, $data, $options);

        // Send in batches
        $results = [];

        // Les jetons sont découpés en parallèle des messages : un ticket
        // en erreur doit pouvoir être rattaché au jeton qui l'a provoqué
        $messageChunks = array_chunk($messages, self::MAX_BATCH_SIZE);
        $tokenChunks   = array_chunk($validTokens, self::MAX_BATCH_SIZE);

        foreach ($messageChunks as $i => $chunk) {
            $response = $this->sendBatch($chunk, $tokenChunks[$i] ?? []);
            $results[] = $response;
        }

        return [
            'success' => true,
            'results' => $results,
        ];
    }

    /**
     * Send notification to user (all devices of user)
     */
    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ) {
        $devices = DeviceToken::where('user_id', $userId)
            ->whereNotNull('fcm_token')
            ->distinct()
            ->pluck('fcm_token')
            ->toArray();

        if (empty($devices)) {
            return false;
        }

        return $this->sendToMultipleDevices($devices, $title, $body, $data, $options);
    }

    /**
     * Send notification to many users
     */
    public function sendToUsers(
        array $userIds,
        string $title,
        string $body,
        array $data = [],
        array $options = []
    ) {
        // Un utilisateur peut posséder plusieurs appareils : tous sont notifiés
        $devices = DeviceToken::whereIn('user_id', $userIds)
            ->whereNotNull('fcm_token')
            ->distinct()
            ->pluck('fcm_token')
            ->toArray();

        if (empty($devices)) {
            return false;
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
     *
     * @param array $tokens Jetons du lot, dans le même ordre que les messages
     */
    private function sendBatch(array $messages, array $tokens = []): array
    {
        try {
            $response = Http::timeout(30)
                ->post(self::EXPO_PUSH_URL, $messages);

            if ($response->successful()) {
                $result = $response->json();

                // Log errors nếu có
                $this->handleTicketErrors($result['data'] ?? [], $tokens);

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
     * Handle ticket errors and deactivate invalid tokens
     *
     * Expo renvoie les tickets dans l'ordre des messages envoyés : la
     * position d'un ticket suffit à retrouver le jeton correspondant.
     */
    private function handleTicketErrors(array $tickets, array $tokens = []): void
    {
        $unregistered = [];

        foreach ($tickets as $index => $ticket) {
            if (($ticket['status'] ?? null) !== 'error') {
                continue;
            }

            $errorCode = $ticket['details']['error'] ?? null;

            // L'appareil a désinstallé l'application ou révoqué son jeton
            if ($errorCode === self::ERROR_UNREGISTERED && isset($tokens[$index])) {
                $unregistered[] = $tokens[$index];
                continue;
            }

            Log::error('Expo Ticket Error', $ticket);
        }

        if (!empty($unregistered)) {
            $this->purgeTokens($unregistered);
        }
    }

    /**
     * Supprime les appareils dont le jeton n'est plus valable.
     */
    private function purgeTokens(array $tokens): void
    {
        try {
            $deleted = DeviceToken::whereIn('fcm_token', $tokens)->delete();

            Log::channel('api')->info('Expo : jetons obsolètes supprimés', [
                'tokens'  => count($tokens),
                'deleted' => $deleted,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['expo-purge-tokens']);
        }
    }

    /**
     * Get receipts to check status
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