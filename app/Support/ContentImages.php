<?php

namespace App\Support;

use App\Models\Media;
use App\Models\Scopes\SiteScope;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Storage;

/**
 * Hoàn thiện thẻ <img> trong nội dung bài khi hiển thị:
 * - ảnh thuộc thư viện của site được gắn đủ alt, width, height, srcset, sizes;
 * - ảnh không thuộc thư viện (không biết kích thước) bị bỏ, để trang không xô lệch;
 * - ảnh đầu tiên tải ngay (thường nằm trong màn hình đầu), các ảnh sau tải lười.
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

        $isFirst = true;
        foreach ($images as $image) {
            assert($image instanceof DOMElement);
            $media = $library[self::directoryOf($image->getAttribute('src')) ?? ''] ?? null;

            if ($media === null) {
                $image->parentNode?->removeChild($image);

                continue;
            }

            $width = $media->largestWidth();
            $image->setAttribute('src', $media->url($width));
            $image->setAttribute('width', (string) $width);
            $image->setAttribute('height', (string) $media->heightAt($width));
            $image->setAttribute('srcset', $media->srcset());
            $image->setAttribute('sizes', self::SIZES);
            if (trim($image->getAttribute('alt')) === '') {
                $image->setAttribute('alt', $media->alt);
            }

            if ($isFirst) {
                $image->setAttribute('fetchpriority', 'high');
                $isFirst = false;
            } else {
                $image->setAttribute('loading', 'lazy');
            }
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

        return self::libraryMediaIn($directories, $siteId);
    }

    /**
     * Ảnh thư viện của chính site này theo thư mục; ảnh site khác không được nhận.
     *
     * @param  list<string>  $directories
     * @return array<string, Media>
     */
    private static function libraryMediaIn(array $directories, int $siteId): array
    {
        if ($directories === []) {
            return [];
        }

        return Media::withoutGlobalScope(SiteScope::class)
            ->where('site_id', $siteId)
            ->whereIn('path', array_values(array_unique($directories)))
            ->get()
            ->keyBy('path')
            ->all();
    }

    /**
     * Mọi src của thẻ <img> trong nội dung, theo thứ tự xuất hiện.
     *
     * @return list<string>
     */
    private static function sources(string $html): array
    {
        return preg_match_all('/<img\b[^>]*\bsrc\s*=\s*["\']([^"\']*)["\']/i', $html, $matches) ? $matches[1] : [];
    }

    /** Ảnh thư viện đầu tiên (của chính site) trong nội dung bài, dùng làm ảnh đại diện khi cần. */
    public static function firstLibraryImage(string $html, int $siteId): ?Media
    {
        $directories = array_values(array_filter(array_map(self::directoryOf(...), self::sources($html))));
        $library = self::libraryMediaIn($directories, $siteId);

        foreach ($directories as $directory) {
            if (isset($library[$directory])) {
                return $library[$directory];
            }
        }

        return null;
    }

    /**
     * Những src trong nội dung không thuộc thư viện của site (dùng để kiểm tra khi lưu bài).
     *
     * @return list<string>
     */
    public static function foreignSources(string $html, int $siteId): array
    {
        $sources = self::sources($html);
        $known = array_keys(self::libraryMediaIn(array_values(array_filter(array_map(self::directoryOf(...), $sources))), $siteId));

        return array_values(array_filter(
            $sources,
            fn (string $src): bool => ! in_array(self::directoryOf($src), $known, true),
        ));
    }

    /** "…/storage/media/3/01J…/960.webp" → "media/3/01J…" */
    public static function directoryOf(string $src): ?string
    {
        $base = rtrim(Storage::disk(Media::DISK)->url(''), '/').'/';
        $path = str_starts_with($src, $base) ? substr($src, strlen($base)) : (string) parse_url($src, PHP_URL_PATH);
        $path = ltrim((string) preg_replace('#^/?storage/#', '', $path), '/');

        return preg_match('#^(media/\d+/[A-Za-z0-9]+)/\d+\.webp$#', $path, $matches) ? $matches[1] : null;
    }
}
