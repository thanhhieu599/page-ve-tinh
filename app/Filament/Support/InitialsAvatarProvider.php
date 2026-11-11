<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ảnh đại diện chữ cái đầu, tạo ngay trong trang (SVG data URI).
 * Thay cho ui-avatars.com: không gửi tên nhân viên ra dịch vụ ngoài, chạy cả khi không có mạng.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $words = preg_split('/\s+/u', trim((string) Filament::getNameForDefaultAvatar($record))) ?: [];
        $initials = mb_strtoupper(implode('', array_map(
            fn (string $word) => mb_substr((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $word), 0, 1),
            array_slice(array_values(array_filter($words)), 0, 2),
        )));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#c1272d"/>'
            .'<text x="32" y="41" font-family="Arial, sans-serif" font-size="26" font-weight="700" fill="#ffffff" text-anchor="middle">'
            .htmlspecialchars($initials !== '' ? $initials : '?', ENT_XML1).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
