<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Loại doanh nghiệp. */
enum CustomerType: string implements HasColor, HasLabel
{
    case Fdi = 'fdi';
    case Domestic = 'domestic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fdi => 'FDI',
            self::Domestic => 'Nội địa',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Fdi => 'primary',
            self::Domestic => 'gray',
        };
    }
}
