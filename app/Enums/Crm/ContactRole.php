<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Vai trò người liên hệ trong quyết định mua (B2B). */
enum ContactRole: string implements HasColor, HasLabel
{
    case Purchasing = 'purchasing';
    case Qa = 'qa';
    case Admin = 'admin';
    case Director = 'director';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchasing => 'Mua hàng (Purchasing)',
            self::Qa => 'QA / EHS',
            self::Admin => 'Hành chính / Kho',
            self::Director => 'Giám đốc / Quản lý',
            self::Other => 'Khác',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Purchasing => 'primary',
            self::Qa => 'warning',
            self::Admin => 'info',
            self::Director => 'success',
            self::Other => 'gray',
        };
    }
}
