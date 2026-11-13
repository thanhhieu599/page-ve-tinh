<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Kênh khách hàng tiềm năng đến. */
enum LeadChannel: string implements HasColor, HasLabel
{
    case Website = 'website';
    case Phone = 'phone';
    case Zalo = 'zalo';
    case Email = 'email';
    case Event = 'event';
    case Referral = 'referral';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Website => 'Form website',
            self::Phone => 'Điện thoại',
            self::Zalo => 'Zalo',
            self::Email => 'Email',
            self::Event => 'Hội chợ / gặp trực tiếp',
            self::Referral => 'Giới thiệu',
            self::Other => 'Khác',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Website => 'primary',
            self::Phone => 'info',
            self::Zalo => 'info',
            self::Email => 'info',
            self::Event => 'warning',
            self::Referral => 'success',
            self::Other => 'gray',
        };
    }
}
