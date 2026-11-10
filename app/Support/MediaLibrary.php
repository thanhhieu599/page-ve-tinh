<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

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

    /** Dung lượng tối đa khi tải lên, tính bằng KB (10 MB). */
    public const MAX_KB = 10240;

    public static function storeUpload(TemporaryUploadedFile $file, string $alt, ?int $siteId): Media
    {
        return self::store($file->getRealPath(), $alt, (int) $siteId);
    }

    public static function store(string $sourcePath, string $alt, int $siteId): Media
    {
        if ($siteId <= 0) {
            throw new InvalidArgumentException('Ảnh phải thuộc về một site.');
        }

        // Đọc ảnh một lần (tự xoay theo EXIF), rồi thu nhỏ dần từ lớn xuống nhỏ để tiết kiệm bộ nhớ.
        $image = (new ImageManager(new Driver))->read($sourcePath);
        $width = $image->width();
        $height = $image->height();

        $widths = array_values(array_filter(self::WIDTHS, fn (int $w): bool => $w < $width));
        $widths[] = min($width, max(self::WIDTHS));
        $widths = array_values(array_unique($widths));
        rsort($widths);

        $media = new Media(['alt' => $alt]);
        $media->site_id = $siteId;
        $media->path = 'media/'.$siteId.'/'.Str::ulid();
        $media->width = $width;
        $media->height = $height;
        $media->size = (int) filesize($sourcePath);
        $media->variants = array_reverse($widths);

        try {
            foreach ($widths as $targetWidth) {
                $image->scaleDown(width: $targetWidth);
                Storage::disk(Media::DISK)->put($media->variantPath($targetWidth), (string) $image->toWebp(self::QUALITY));
            }

            $media->save();
        } catch (Throwable $exception) {
            Storage::disk(Media::DISK)->deleteDirectory($media->path);

            throw $exception;
        }

        return $media;
    }
}
