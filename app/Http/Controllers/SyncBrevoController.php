<?php

namespace App\Http\Controllers;

use App\Jobs\SyncUserToBrevo;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SyncBrevoController extends Controller
{
    private const BASE_URL = 'https://api.brevo.com/v3';

    /**
     * Sync tất cả user lên Brevo.
     * Route: GET /sync-brevo/users
     */
    public function syncUsers(): JsonResponse
    {
        $query = User::query()->select('id'); // chỉ cần id để dispatch

        // $query->whereNull('brevo_id');
        // if ($request->filled('id')) {
        //     $query->where('id', $request->input('id'));
        // } else {
        //     $query->whereNull('brevo_id');
        // }

        $count = 0;
        $query->chunkById(500, function ($users) use (&$count) {
            foreach ($users as $user) {
                SyncUserToBrevo::dispatch($user->id);
                $count++;
            }
        });

        return response()->json([
            'message'   => 'Đã đẩy job vào queue, đang xử lý nền.',
            'dispatched' => $count,
        ]);
    }

    /**
     * Tạo contact. Trả về brevo id nếu thành công, null nếu email đã tồn tại.
     */
    private function createContact(array $payload): ?int
    {
        $response = $this->client()->post(self::BASE_URL . '/contacts', $payload);

        if ($response->successful()) {
            return $response->json('id'); // Brevo trả về {"id": ...}
        }

        // 400 + code "duplicate_parameter" = email đã tồn tại
        if ($response->status() === 400 && $response->json('code') === 'duplicate_parameter') {
            return null;
        }

        // Lỗi khác → ném ra để nhánh catch ghi lại
        throw new \RuntimeException($response->body());
    }

    /**
     * Update contact theo identifier (email hoặc contact_id).
     */
    private function updateContact(string $identifier, array $payload): void
    {
        $response = $this->client()->put(
            self::BASE_URL . '/contacts/' . rawurlencode($identifier),
            $payload
        );

        if (! $response->successful()) {
            throw new \RuntimeException($response->body());
        }
    }

    /**
     * Lấy brevo id của contact theo email. Null nếu không có.
     */
    private function getContactIdByEmail(string $email): ?int
    {
        $response = $this->client()->get(
            self::BASE_URL . '/contacts/' . rawurlencode($email)
        );

        return $response->successful() ? $response->json('id') : null;
    }

    private function client()
    {
        return Http::withHeaders([
            'api-key'      => config('services.brevo.key'),
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ]);
    }
}
