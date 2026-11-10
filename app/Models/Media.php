<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Một ảnh trong thư viện của site. Ảnh được lưu thành nhiều bản WebP theo chiều rộng
 * (xem App\Support\MediaLibrary); alt là bắt buộc.
 */
class Media extends Model
{
    use BelongsToSite;

    public const DISK = 'public';

    protected $fillable = ['alt'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
            'variants' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Media $media): void {
            Storage::disk(self::DISK)->deleteDirectory($media->path);
        });
    }

    /** @return list<int> */
    public function variantWidths(): array
    {
        $widths = array_map('intval', $this->variants ?? []);
        sort($widths);

        return $widths;
    }

    public function largestWidth(): int
    {
        $widths = $this->variantWidths();

        return $widths === [] ? $this->width : end($widths);
    }

    public function variantPath(int $width): string
    {
        return $this->path.'/'.$width.'.webp';
    }

    /** URL công khai của bản ở chiều rộng cho trước (mặc định: bản lớn nhất). */
    public function url(?int $width = null): string
    {
        return Storage::disk(self::DISK)->url($this->variantPath($width ?? $this->largestWidth()));
    }

    /** Chiều cao tương ứng với một chiều rộng, giữ đúng tỉ lệ ảnh gốc. */
    public function heightAt(int $width): int
    {
        return (int) round($this->height * $width / max(1, $this->width));
    }

    public function srcset(): string
    {
        return collect($this->variantWidths())
            ->map(fn (int $width): string => $this->url($width).' '.$width.'w')
            ->implode(', ');
    }
}
