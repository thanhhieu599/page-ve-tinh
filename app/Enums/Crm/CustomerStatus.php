<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Trạng thái khách hàng doanh nghiệp. */
enum CustomerStatus: string implements HasColor, HasLabel
{
    case Prospect = 'prospect';
    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Prospect => 'Tiềm năng',
            self::Active => 'Đang giao dịch',
            self::Inactive => 'Ngừng giao dịch',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Prospect => 'info',
            self::Active => 'success',
            self::Inactive => 'gray',
        };
    }
}
