<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Mục đích tìm kiếm của từ khoá, để chọn đúng loại trang đích. */
enum KeywordIntent: string implements HasLabel
{
    case Informational = 'informational';
    case Commercial = 'commercial';
    case Transactional = 'transactional';
    case Navigational = 'navigational';

    public function getLabel(): string
    {
        return match ($this) {
            self::Informational => 'Tìm hiểu (bài kiến thức)',
            self::Commercial => 'So sánh, cân nhắc',
            self::Transactional => 'Mua / báo giá',
            self::Navigational => 'Tìm thương hiệu',
        };
    }
}
