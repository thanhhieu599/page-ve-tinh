<?php

namespace App\Enums;

/**
 * Vai trò trong trang quản trị. Giá trị là tên role của spatie/laravel-permission.
 */
enum Role: string
{
    /** Toàn quyền, thấy mọi site. */
    case Admin = 'admin';

    /** Duyệt và đăng bài trên các site được gán. */
    case Editor = 'editor';

    /** Viết và gửi duyệt trên các site được gán, không tự đăng. */
    case Writer = 'writer';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Editor => 'Biên tập',
            self::Writer => 'Người viết',
        };
    }
}
