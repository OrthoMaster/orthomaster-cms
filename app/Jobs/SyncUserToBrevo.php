<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SyncUserToBrevo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BASE_URL = 'https://api.brevo.com/v3';

    public int $tries = 3;          // thử lại tối đa 3 lần nếu lỗi
    public int $backoff = 10;       // chờ 10s giữa các lần thử lại

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        $user = User::with('profession')->find($this->userId);

        // User bị xóa hoặc không có email → bỏ qua
        if (! $user || empty($user->email)) {
            return;
        }

        $listId = (int) config('services.brevo.user_list_id');

        $attributes = [
            'NOM'        => $user->name,
            'PRENOM'     => $user->first_name,
            'PROFESSION' => $user->profession?->name,
            'SYNC_CMS'   => true,
        ];

        if ($user->brevo_id) {
            // Đã có id → update theo contact_id, ghi đầy đủ attributes + list
            $this->updateContact((string) $user->brevo_id, [
                'attributes' => array_merge($attributes, ['EMAIL' => $user->email]),
                'listIds'    => [$listId],
            ]);
            return;
        }

        // Chưa có id → tạo mới
        $brevoId = $this->createContact([
            'email'         => $user->email,
            'attributes'    => $attributes,
            'listIds'       => [$listId],
            'updateEnabled' => true,
        ]);

        // Trùng email (đã tồn tại trên Brevo) → update đầy đủ như create
        if ($brevoId === null) {
            // update theo email: ghi cùng bộ attributes + đưa vào list
            $this->updateContact($user->email, [
                'attributes' => $attributes,
                'listIds'    => [$listId],
            ]);

            // lấy lại id để lưu về DB
            $brevoId = $this->getContactIdByEmail($user->email);
        }

        if ($brevoId !== null) {
            $user->update(['brevo_id' => $brevoId]);
        }
    }

    private function createContact(array $payload): ?int
    {
        $response = $this->client()->post(self::BASE_URL . '/contacts', $payload);

        if ($response->successful()) {
            return $response->json('id');
        }

        if ($response->status() === 400 && $response->json('code') === 'duplicate_parameter') {
            return null;
        }

        throw new \RuntimeException($response->body());
    }

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
