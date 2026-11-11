<?php

namespace App\Support;

/**
 * Biểu tượng cho khối cam kết "Vì sao chọn …" của trang giới thiệu: biên tập chọn từ danh sách,
 * không phải tìm ảnh. SVG nét (24×24, stroke = màu chữ) nhúng thẳng vào trang.
 */
final class AboutIcons
{
    /** key => [nhãn trong quản trị, đường vẽ SVG] */
    private const ICONS = [
        'truck' => ['Giao hàng', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4z'],
        'invoice' => ['Hoá đơn, chứng từ', 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6M9 16h3'],
        'shield' => ['Chất lượng, tiêu chuẩn', 'M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6zM8.5 12l2.5 2.5 4.5-5'],
        'headset' => ['Hỗ trợ, tư vấn', 'M4 14v-2a8 8 0 0 1 16 0v2M4 14h3v5H5a1 1 0 0 1-1-1zm16 0h-3v5h2a1 1 0 0 0 1-1zM17 19c0 1-1.5 2-5 2'],
        'tag' => ['Giá tốt theo số lượng', 'M3 12V4h8l10 10-8 8zM7.5 8.5h.01'],
        'box' => ['Kho hàng, đa dạng mã', 'M21 8l-9-5-9 5 9 5zM3 8v8l9 5 9-5V8M12 13v8'],
        'clock' => ['Đúng hẹn, nhanh', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2'],
        'factory' => ['Phục vụ nhà máy', 'M3 21V10l6 4V10l6 4V6l6-3v18zM7 17h2m4 0h2'],
    ];

    /** @return array<string, string> key => nhãn (cho ô chọn trong quản trị) */
    public static function options(): array
    {
        return array_map(fn (array $icon) => $icon[0], self::ICONS);
    }

    public static function has(?string $key): bool
    {
        return is_string($key) && isset(self::ICONS[$key]);
    }

    public static function svg(?string $key): string
    {
        $path = self::ICONS[self::has($key) ? $key : 'shield'][1];

        return '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.$path.'"/></svg>';
    }
}
