<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Http\Request;

/**
 * URL chuẩn của trang đang xem, cho thẻ canonical: domain của site, đường dẫn chuẩn
 * (chữ thường, không "/" cuối), bỏ mọi tham số (utm, gclid…).
 *
 * Trang danh sách có phân trang gọi với $withPage để giữ số trang (> 1): mỗi trang
 * danh sách tự là bản chuẩn của chính nó.
 */
final class CanonicalUrl
{
    public static function for(Site $site, Request $request, bool $withPage = false): string
    {
        $url = $site->url(CanonicalPath::from($request->getPathInfo()));
        $page = $request->query('page');

        if ($withPage && is_string($page) && ctype_digit($page) && (int) $page > 1) {
            $url .= '?page='.(int) $page;
        }

        return $url;
    }
}
