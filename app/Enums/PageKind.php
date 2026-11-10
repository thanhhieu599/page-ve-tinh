<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Ba trang bắt buộc mỗi site phải có (minh bạch chủ sở hữu, điều kiện mở index). */
enum PageKind: string implements HasLabel
{
    case About = 'gioi-thieu';
    case Privacy = 'chinh-sach-quyen-rieng-tu';
    case Contact = 'lien-he';

    public function getLabel(): string
    {
        return match ($this) {
            self::About => 'Giới thiệu',
            self::Privacy => 'Chính sách quyền riêng tư',
            self::Contact => 'Liên hệ',
        };
    }

    /** Tiêu đề mặc định khi tạo trang nháp. */
    public function defaultTitle(): string
    {
        return match ($this) {
            self::About => 'Giới thiệu công ty',
            self::Privacy => 'Chính sách quyền riêng tư và bảo vệ dữ liệu cá nhân',
            self::Contact => 'Liên hệ và yêu cầu báo giá',
        };
    }

    public function path(): string
    {
        return '/'.$this->value;
    }
}
