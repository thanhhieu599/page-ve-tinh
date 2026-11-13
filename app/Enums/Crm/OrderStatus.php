<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Trạng thái đơn hàng. */
enum OrderStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Processing = 'processing';
    case Shipping = 'shipping';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Mới',
            self::Processing => 'Đang xử lý',
            self::Shipping => 'Đang giao',
            self::Completed => 'Hoàn tất',
            self::Cancelled => 'Đã huỷ',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Processing => 'warning',
            self::Shipping => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }
}
