<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Loại hoạt động chăm sóc khách hàng. */
enum ActivityType: string implements HasColor, HasLabel
{
    case Call = 'call';
    case Email = 'email';
    case Zalo = 'zalo';
    case Meeting = 'meeting';
    case Note = 'note';

    public function getLabel(): string
    {
        return match ($this) {
            self::Call => 'Cuộc gọi',
            self::Email => 'Email',
            self::Zalo => 'Zalo',
            self::Meeting => 'Gặp mặt',
            self::Note => 'Ghi chú',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Call => 'primary',
            self::Email => 'info',
            self::Zalo => 'info',
            self::Meeting => 'success',
            self::Note => 'gray',
        };
    }
}
