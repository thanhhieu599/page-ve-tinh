<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Theme của site: một thư mục trong config('sites.themes_path') có layout.blade.php.
 * Thêm theme = thêm thư mục, không sửa code lõi.
 */
final class Theme
{
    /** @return list<string> tên các theme đang có */
    public static function available(): array
    {
        $themes = [];
        foreach (glob(self::root().'/*/layout.blade.php') ?: [] as $layout) {
            $themes[] = basename(dirname($layout));
        }
        sort($themes);

        return $themes;
    }

    public static function exists(string $name): bool
    {
        return in_array($name, self::available(), true);
    }

    /**
     * Theme dùng thật cho site: thư mục theme bị xoá/đổi tên thì dùng theme mặc định
     * (ghi log lỗi) thay vì làm cả site lỗi 500.
     */
    public static function resolve(string $name): string
    {
        if (self::exists($name)) {
            return $name;
        }

        Log::error('Không tìm thấy thư mục theme, dùng theme mặc định.', ['theme' => $name]);

        return (string) config('sites.default_theme');
    }

    public static function path(string $name): string
    {
        return self::root().'/'.$name;
    }

    private static function root(): string
    {
        return rtrim((string) config('sites.themes_path'), '/');
    }
}
