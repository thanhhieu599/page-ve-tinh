<?php

namespace App\Support;

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

    public static function path(string $name): string
    {
        return self::root().'/'.$name;
    }

    private static function root(): string
    {
        return rtrim((string) config('sites.themes_path'), '/');
    }
}
