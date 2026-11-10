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

    /** Nhãn trong checklist mở index. */
    public function readinessLabel(): string
    {
        return match ($this) {
            self::About => 'Trang giới thiệu công ty',
            self::Privacy => 'Chính sách quyền riêng tư',
            self::Contact => 'Trang liên hệ',
        };
    }

    /** Thứ tự trong menu và footer: giới thiệu, chính sách, liên hệ. */
    public function menuOrder(): int
    {
        return match ($this) {
            self::About => 0,
            self::Privacy => 1,
            self::Contact => 2,
        };
    }

    /** Trang có form yêu cầu báo giá ngay trên trang. */
    public function hasQuoteForm(): bool
    {
        return $this === self::Contact;
    }

    public function path(): string
    {
        return '/'.$this->value;
    }
}
