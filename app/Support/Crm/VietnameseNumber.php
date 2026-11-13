<?php

namespace App\Support\Crm;

/** Đọc số tiền bằng chữ tiếng Việt (dòng "Bằng chữ" trên báo giá, hoá đơn). */
final class VietnameseNumber
{
    private const DIGITS = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];

    private const UNITS = ['', ' nghìn', ' triệu', ' tỷ', ' nghìn tỷ', ' triệu tỷ'];

    public static function words(int $number): string
    {
        if ($number === 0) {
            return 'Không đồng';
        }
        $groups = [];
        while ($number > 0) {
            $groups[] = $number % 1000;
            $number = intdiv($number, 1000);
        }
        $parts = [];
        $count = count($groups);
        for ($i = $count - 1; $i >= 0; $i--) {
            if ($groups[$i] === 0) {
                continue;
            }
            $parts[] = self::group($groups[$i], $i < $count - 1).self::UNITS[$i];
        }

        return mb_strtoupper(mb_substr($text = implode(' ', $parts).' đồng', 0, 1)).mb_substr($text, 1);
    }

    /** Đọc nhóm 3 chữ số; $full: đọc đủ "không trăm" khi không phải nhóm đầu. */
    private static function group(int $n, bool $full): string
    {
        [$h, $t, $u] = [intdiv($n, 100), intdiv($n % 100, 10), $n % 10];
        $out = [];
        if ($h > 0 || $full) {
            $out[] = self::DIGITS[$h].' trăm';
        }
        if ($t > 1) {
            $out[] = self::DIGITS[$t].' mươi';
        } elseif ($t === 1) {
            $out[] = 'mười';
        } elseif ($u > 0 && ($h > 0 || $full)) {
            $out[] = 'lẻ';
        }
        if ($u > 0) {
            $out[] = match (true) {
                $u === 1 && $t > 1 => 'mốt',
                $u === 5 && $t > 0 => 'lăm',
                $u === 4 && $t > 1 => 'tư',
                default => self::DIGITS[$u],
            };
        }

        return implode(' ', $out);
    }
}
