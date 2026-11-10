<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Đưa ảnh vào thư viện: tạo các bản WebP theo chiều rộng chuẩn (không phóng to ảnh nhỏ)
 * và ghi bản ghi Media cho site.
 */
final class MediaLibrary
{
    /** Chiều rộng chuẩn cho ảnh trong bài (điện thoại, máy tính bảng, máy tính). */
    public const WIDTHS = [480, 960, 1600];

    public const QUALITY = 80;

    public const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function store(string $sourcePath, string $alt, int $siteId): Media
    {
        $manager = new ImageManager(new Driver);
        $original = $manager->read($sourcePath);
        $width = $original->width();
        $height = $original->height();

        $directory = 'media/'.$siteId.'/'.Str::ulid();
        $widths = array_values(array_filter(self::WIDTHS, fn (int $w): bool => $w < $width));
        $widths[] = min($width, max(self::WIDTHS));
        $widths = array_values(array_unique($widths));

        foreach ($widths as $targetWidth) {
            $variant = $manager->read($sourcePath)->scaleDown(width: $targetWidth);
            Storage::disk(Media::DISK)->put(
                $directory.'/'.$targetWidth.'.webp',
                (string) $variant->toWebp(self::QUALITY),
            );
        }

        $media = new Media(['alt' => $alt]);
        $media->site_id = $siteId;
        $media->path = $directory;
        $media->width = $width;
        $media->height = $height;
        $media->size = (int) filesize($sourcePath);
        $media->variants = $widths;
        $media->save();

        return $media;
    }
}
