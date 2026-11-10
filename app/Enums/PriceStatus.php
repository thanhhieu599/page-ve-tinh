<?php

namespace App\Enums;

/** Tình trạng giá tham khảo của sản phẩm trên site. */
enum PriceStatus
{
    case None;
    case Fresh;
    case Expiring;
    case Expired;

    public function color(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Fresh => 'success',
            self::Expiring => 'warning',
            self::Expired => 'danger',
        };
    }
}
