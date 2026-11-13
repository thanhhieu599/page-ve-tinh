<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Bước của báo giá trong quy trình bán hàng. */
enum QuoteStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Negotiating = 'negotiating';
    case Won = 'won';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Sent => 'Đã gửi',
            self::Negotiating => 'Đàm phán',
            self::Won => 'Thắng',
            self::Lost => 'Thua',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Negotiating => 'warning',
            self::Won => 'success',
            self::Lost => 'danger',
        };
    }
}
