<?php

namespace App\Support;

use App\Models\CompanyProfile;
use App\Models\Product;
use App\Models\Scopes\SiteScope;
use App\Models\Site;
use DOMDocument;
use DOMElement;

/**
 * Chính sách liên kết trong nội dung bài, để các site không bị Google coi là mạng lưới
 * trang vệ tinh:
 * - không link sang domain (kể cả subdomain) của site vệ tinh khác trong hệ thống;
 * - link sang trangthietbi.vn chỉ khi có ngữ cảnh mua hàng/báo giá: link mua của một
 *   sản phẩm đang bán trên site, hoặc bài được Biên tập/Admin đánh dấu ngữ cảnh mua hàng.
 *
 * Link được đọc bằng trình phân tích HTML (href không nháy vẫn bắt được) và chuẩn hoá
 * host (chữ hoa, dấu chấm cuối, www, "\" thay "/", tên miền tiếng Việt dạng punycode).
 */
final class ContentLinkRules
{
    /**
     * Các lỗi vi phạm, mỗi lỗi một câu có nêu đúng link vi phạm. Rỗng nghĩa là hợp lệ.
     *
     * @return list<string>
     */
    public static function violations(string $html, Site $site, bool $hasPurchaseContext): array
    {
        $hrefs = self::hrefs($html);
        if ($hrefs === []) {
            return [];
        }

        $satellites = Site::query()->whereKeyNot($site->id)->pluck('domain')
            ->map(fn (string $domain) => self::bareHost($domain))->all();

        $toSatellites = array_filter($hrefs, function (string $href) use ($satellites): bool {
            $host = self::hostOf($href);

            return $host !== '' && array_filter($satellites, fn (string $satellite) => self::isSameOrSubdomain($host, $satellite)) !== [];
        });

        $toStore = [];
        if (! $hasPurchaseContext) {
            $buyUrls = self::buyUrls($site);
            $toStore = array_filter(
                $hrefs,
                fn (string $href): bool => self::isStore(self::hostOf($href)) && ! in_array(self::normalize($href), $buyUrls, true),
            );
        }

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
        $store = self::bareHost(CompanyProfile::current()->host());

        return $store !== '' && self::isSameOrSubdomain($host, $store);
    }

    private static function isSameOrSubdomain(string $host, string $domain): bool
    {
        return $host === $domain || str_ends_with($host, '.'.$domain);
    }

    /** host không www + đường dẫn không "/" cuối + query; bỏ scheme và #fragment. */
    private static function normalize(string $url): string
    {
        $url = self::clean($url);
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');
        $query = parse_url($url, PHP_URL_QUERY);

        return self::hostOf($url).$path.($query ? '?'.$query : '');
    }

    /** @return list<string> */
    private static function hrefs(string $html): array
    {
        if (stripos($html, '<a') === false) {
            return [];
        }

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $hrefs = [];
        foreach ($document->getElementsByTagName('a') as $link) {
            if ($link instanceof DOMElement && $link->hasAttribute('href')) {
                $hrefs[] = trim($link->getAttribute('href'));
            }
        }

        return $hrefs;
    }

    /** Trình duyệt coi "\" như "/" trong URL (https:\\host\path). */
    private static function clean(string $url): string
    {
        return str_replace('\\', '/', trim($url));
    }

    private static function hostOf(string $url): string
    {
        return self::bareHost((string) parse_url(self::clean($url), PHP_URL_HOST));
    }

    private static function bareHost(string $host): string
    {
        $host = strtolower(trim($host, '.'));
        $ascii = $host !== '' && function_exists('idn_to_ascii') ? idn_to_ascii($host) : false;

        return (string) preg_replace('/^www\./', '', $ascii !== false ? $ascii : $host);
    }
}
