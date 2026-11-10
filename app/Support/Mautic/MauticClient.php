<?php

namespace App\Support\Mautic;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gọi Mautic REST API (xác thực Basic Auth, cần bật "API" và "HTTP basic auth" trong Mautic).
 * Thông tin đăng nhập lấy từ biến môi trường, không lưu trong code.
 */
final class MauticClient
{
    public static function configured(): bool
    {
        return filled(config('services.mautic.url'))
            && filled(config('services.mautic.username'))
            && filled(config('services.mautic.password'));
    }

    /**
     * Tạo hoặc cập nhật contact (Mautic gộp theo email). Trả về id contact.
     *
     * @param  array<string, mixed>  $fields
     */
    public function upsertContact(array $fields): int
    {
        $id = $this->post('/api/contacts/new', $fields)['contact']['id'] ?? null;

        if (! is_int($id)) {
            throw new RuntimeException('Mautic không trả về id contact.');
        }

        return $id;
    }

    /** @param  array<string, string>  $utm */
    public function addUtm(int $contactId, array $utm): void
    {
        $this->post("/api/contacts/{$contactId}/utm/add", $utm);
    }

    public function addNote(int $contactId, string $text): void
    {
        $this->post('/api/notes/new', ['lead' => $contactId, 'text' => $text, 'type' => 'general']);
    }

    public function addToCampaign(int $campaignId, int $contactId): void
    {
        $this->post("/api/campaigns/{$campaignId}/contact/{$contactId}/add", []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function post(string $path, array $data): array
    {
        // Lỗi HTTP (4xx/5xx, mất kết nối) ném ngoại lệ để job thử lại.
        return (array) $this->http()->post($path, $data)->throw()->json();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.mautic.url'), '/'))
            ->withBasicAuth((string) config('services.mautic.username'), (string) config('services.mautic.password'))
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }
}
