<?php

namespace App\Support;

/**
 * Dạng chuẩn của đường dẫn: chữ thường, bỏ đuôi .html, bỏ "/" cuối (trừ trang chủ),
 * luôn bắt đầu bằng "/".
 */
final class CanonicalPath
{
    public static function from(string $path): string
    {
        $path = trim(strtolower($path), '/');
        $path = (string) preg_replace('/\.html$/', '', $path);

        return '/'.trim($path, '/');
    }
}
