<?php

namespace App\Support;

use App\Models\Media;
use App\Models\Scopes\SiteScope;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Storage;

/**
 * Hoàn thiện thẻ <img> trong nội dung bài khi hiển thị: ảnh thuộc thư viện của site
 * được gắn đủ alt, width, height, srcset, sizes; mọi ảnh trong bài đều tải lười.
 */
final class ContentImages
{
    /** Bề rộng cột nội dung tối đa trên máy tính, dùng cho thuộc tính sizes. */
    public const SIZES = '(max-width: 800px) 100vw, 800px';

    public static function enhance(string $html, int $siteId): string
    {
        if (! str_contains($html, '<img')) {
            return $html;
        }

        $document = new DOMDocument;
        // Bọc trong thẻ có khai báo UTF-8 để giữ đúng tiếng Việt.
        @$document->loadHTML('<?xml encoding="UTF-8"><div id="content-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $images = iterator_to_array($document->getElementsByTagName('img'));
        $library = self::libraryMediaFor($images, $siteId);

        foreach ($images as $image) {
            assert($image instanceof DOMElement);
            $media = $library[self::directoryOf($image->getAttribute('src')) ?? ''] ?? null;

            if ($media !== null) {
                $width = $media->largestWidth();
                $image->setAttribute('src', $media->url($width));
                $image->setAttribute('width', (string) $width);
                $image->setAttribute('height', (string) $media->heightAt($width));
                $image->setAttribute('srcset', $media->srcset());
                $image->setAttribute('sizes', self::SIZES);
                if (trim($image->getAttribute('alt')) === '') {
                    $image->setAttribute('alt', $media->alt);
                }
            } elseif (! $image->hasAttribute('alt')) {
                $image->setAttribute('alt', '');
            }

            $image->setAttribute('loading', 'lazy');
            $image->setAttribute('decoding', 'async');
        }

        $root = $document->getElementById('content-root');
        $output = '';
        foreach ($root?->childNodes ?? [] as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    /**
     * @param  list<\DOMNode>  $images
     * @return array<string, Media> theo thư mục ảnh
     */
    private static function libraryMediaFor(array $images, int $siteId): array
    {
        $directories = [];
        foreach ($images as $image) {
            if ($image instanceof DOMElement && ($directory = self::directoryOf($image->getAttribute('src'))) !== null) {
                $directories[] = $directory;
            }
        }

        if ($directories === []) {
            return [];
        }

        // Chỉ ảnh của chính site này; ảnh site khác không được gắn thông tin thư viện.
        return Media::withoutGlobalScope(SiteScope::class)
            ->where('site_id', $siteId)
            ->whereIn('path', array_unique($directories))
            ->get()
            ->keyBy('path')
            ->all();
    }

    /** "…/storage/media/3/01J…/960.webp" → "media/3/01J…" */
    private static function directoryOf(string $src): ?string
    {
        $base = rtrim(Storage::disk(Media::DISK)->url(''), '/').'/';
        $path = str_starts_with($src, $base) ? substr($src, strlen($base)) : (string) parse_url($src, PHP_URL_PATH);
        $path = ltrim((string) preg_replace('#^/?storage/#', '', $path), '/');

        return preg_match('#^(media/\d+/[A-Za-z0-9]+)/\d+\.webp$#', $path, $matches) ? $matches[1] : null;
    }
}
