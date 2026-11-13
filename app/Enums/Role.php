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

    /** CRM: trưởng phòng kinh doanh, thấy mọi khách hàng/lead, phân lead. */
    case SalesManager = 'sales-manager';

    /** CRM: nhân viên kinh doanh, chỉ thấy lead/khách/báo giá/đơn được giao. */
    case Sales = 'sales';

    /** Vai trò vào trang quản trị nội dung các site. @return list<string> */
    public static function contentValues(): array
    {
        return [self::Admin->value, self::Editor->value, self::Writer->value];
    }

    /** Vai trò vào CRM. @return list<string> */
    public static function crmValues(): array
    {
        return [self::Admin->value, self::SalesManager->value, self::Sales->value];
    }

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
            self::SalesManager => 'Trưởng phòng kinh doanh',
            self::Sales => 'Nhân viên kinh doanh',
        };
    }
}
