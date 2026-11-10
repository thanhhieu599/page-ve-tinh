<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Trạng thái theo dõi bán hàng của một lead. */
enum LeadStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Qualified = 'qualified';
    case Quoted = 'quoted';
    case Won = 'won';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Mới',
            self::Qualified => 'Đủ chuẩn',
            self::Quoted => 'Đã báo giá',
            self::Won => 'Thắng',
            self::Lost => 'Thua',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Qualified => 'primary',
            self::Quoted => 'warning',
            self::Won => 'success',
            self::Lost => 'gray',
        };
    }
}
