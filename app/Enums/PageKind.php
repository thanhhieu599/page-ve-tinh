<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Ba trang bắt buộc mỗi site phải có (minh bạch chủ sở hữu, điều kiện mở index). */
enum PageKind: string implements HasLabel
{
    case About = 'gioi-thieu';
    case Privacy = 'chinh-sach-quyen-rieng-tu';
    case Contact = 'lien-he';
    case Shipping = 'chinh-sach-van-chuyen';
    case Returns = 'chinh-sach-doi-tra';
    case Payment = 'hinh-thuc-thanh-toan';

    public function getLabel(): string
    {
        return match ($this) {
            self::About => 'Giới thiệu',
            self::Privacy => 'Chính sách quyền riêng tư',
            self::Contact => 'Liên hệ',
            self::Shipping => 'Chính sách vận chuyển',
            self::Returns => 'Chính sách đổi trả',
            self::Payment => 'Hình thức thanh toán',
        };
    }

    /** Trang bắt buộc trước khi mở index (minh bạch chủ sở hữu); trang chính sách khác là tuỳ chọn. */
    public function isRequired(): bool
    {
        return in_array($this, [self::About, self::Privacy, self::Contact], true);
    }

    /** @return list<self> */
    public static function required(): array
    {
        return array_values(array_filter(self::cases(), fn (self $kind) => $kind->isRequired()));
    }

    /** Tiêu đề mặc định khi tạo trang nháp. */
    public function defaultTitle(): string
    {
        return match ($this) {
            self::About => 'Giới thiệu công ty',
            self::Privacy => 'Chính sách quyền riêng tư và bảo vệ dữ liệu cá nhân',
            self::Contact => 'Liên hệ và yêu cầu báo giá',
            self::Shipping => 'Chính sách vận chuyển và giao nhận',
            self::Returns => 'Chính sách đổi trả hàng',
            self::Payment => 'Hình thức thanh toán',
        };
    }

    /** Nhãn trong checklist mở index. */
    public function readinessLabel(): string
    {
        return match ($this) {
            self::About => 'Trang giới thiệu công ty',
            self::Privacy => 'Chính sách quyền riêng tư',
            self::Contact => 'Trang liên hệ',
            self::Shipping, self::Returns, self::Payment => $this->getLabel(),
        };
    }

    /** Thứ tự trong menu và footer: giới thiệu, chính sách, liên hệ. */
    public function menuOrder(): int
    {
        return match ($this) {
            self::About => 0,
            self::Privacy => 1,
            self::Shipping => 2,
            self::Returns => 3,
            self::Payment => 4,
            self::Contact => 5,
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
