<?php

namespace App\Support;

/** Họ tên người Việt. */
final class VietnameseName
{
    /**
     * Tách tên và họ: tên là từ cuối, họ và tên đệm là phần còn lại.
     *
     * @return array{0: string, 1: string} [tên, họ và tên đệm]
     */
    public static function split(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $first = (string) array_pop($parts);

        return [$first, implode(' ', $parts)];
    }
}
