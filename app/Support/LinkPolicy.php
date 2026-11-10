<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Scopes\SiteScope;
use App\Models\Site;

/**
 * Chính sách liên kết trong nội dung bài, để các site không bị Google coi là mạng lưới
 * trang vệ tinh:
 * - không link sang domain của site vệ tinh khác trong hệ thống;
 * - link sang trangthietbi.vn chỉ khi có ngữ cảnh mua hàng/báo giá: link mua của một
 *   sản phẩm đang bán trên site, hoặc bài được Biên tập/Admin đánh dấu ngữ cảnh mua hàng.
 */
final class LinkPolicy
{
    /**
     * Các lỗi vi phạm, mỗi lỗi một câu có nêu đúng link vi phạm. Rỗng nghĩa là hợp lệ.
     *
     * @return list<string>
     */
    public static function violations(string $html, Site $site, bool $hasPurchaseContext): array
    {
        $hrefs = self::hrefs($html);
        $satellites = Site::query()->whereKeyNot($site->id)->pluck('domain')
            ->map(fn (string $domain) => self::bareHost($domain))->all();

        $toSatellites = array_filter($hrefs, fn (string $href): bool => in_array(self::hostOf($href), $satellites, true));
        $toStore = $hasPurchaseContext ? [] : array_filter(
            $hrefs,
            fn (string $href): bool => self::isStore(self::hostOf($href)) && ! in_array(self::normalize($href), self::buyUrls($site), true),
        );

        return array_values(array_filter([
            $toSatellites === [] ? null : 'Không được link sang site vệ tinh khác (Google có thể coi là mạng lưới liên kết): '
                .implode(', ', array_unique($toSatellites)),
            $toStore === [] ? null : 'Link sang trangthietbi.vn chỉ được dùng cho link mua của sản phẩm đang bán trên site, '
                .'hoặc bài có ngữ cảnh mua hàng/báo giá (Biên tập/Admin bật): '.implode(', ', array_unique($toStore)),
        ]));
    }

    /** Link mua (đã chuẩn hoá) của các sản phẩm đang bán trên site. @return list<string> */
    private static function buyUrls(Site $site): array
    {
        return Product::withoutGlobalScope(SiteScope::class)
            ->where('site_id', $site->id)->active()->whereNotNull('buy_url')->pluck('buy_url')
            ->map(fn (string $url) => self::normalize($url))->values()->all();
    }

    private static function isStore(string $host): bool
    {
        $store = self::bareHost((string) parse_url((string) config('company.url'), PHP_URL_HOST));

        return $store !== '' && ($host === $store || str_ends_with($host, '.'.$store));
    }

    /** host không www + đường dẫn không "/" cuối + query; bỏ scheme và #fragment. */
    private static function normalize(string $url): string
    {
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');
        $query = parse_url($url, PHP_URL_QUERY);

        return self::hostOf($url).$path.($query ? '?'.$query : '');
    }

    /** @return list<string> */
    private static function hrefs(string $html): array
    {
        return preg_match_all('/<a\b[^>]*\bhref\s*=\s*["\']([^"\']*)["\']/i', $html, $matches)
            ? array_map(fn (string $href) => html_entity_decode($href), $matches[1])
            : [];
    }

    private static function hostOf(string $url): string
    {
        return self::bareHost((string) parse_url($url, PHP_URL_HOST));
    }

    private static function bareHost(string $host): string
    {
        return (string) preg_replace('/^www\./', '', strtolower(trim($host, '.')));
    }
}
