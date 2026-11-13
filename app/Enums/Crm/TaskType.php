<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Loại việc cần làm. */
enum TaskType: string implements HasColor, HasLabel
{
    case Callback = 'callback';
    case SendQuote = 'send_quote';
    case FollowUp = 'follow_up';
    case Care = 'care';
    case Reorder = 'reorder';
    case Collect = 'collect';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Callback => 'Gọi lại',
            self::SendQuote => 'Gửi báo giá',
            self::FollowUp => 'Theo dõi báo giá',
            self::Care => 'Chăm sóc khách',
            self::Reorder => 'Nhắc tái đặt hàng',
            self::Collect => 'Thu công nợ',
            self::Other => 'Khác',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Callback => 'primary',
            self::SendQuote => 'warning',
            self::FollowUp => 'info',
            self::Care => 'success',
            self::Reorder => 'danger',
            self::Collect => 'danger',
            self::Other => 'gray',
        };
    }
}
