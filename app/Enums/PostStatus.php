<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Vòng đời bài viết: Nháp → Chờ duyệt → Đã đăng.
 */
enum PostStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Pending => 'Chờ duyệt',
            self::Published => 'Đã đăng',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Pending => 'warning',
            self::Published => 'success',
        };
    }
}
