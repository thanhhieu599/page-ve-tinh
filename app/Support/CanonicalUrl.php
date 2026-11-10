<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * URL chuẩn của trang đang xem, cho thẻ canonical.
 *
 * Middleware RedirectToCanonicalUrl đã đưa request GET về đúng scheme/host chuẩn, nên chỉ
 * cần chuẩn hoá đường dẫn và bỏ tham số theo dõi (utm, gclid…). Chỉ giữ số trang (> 1)
 * để mỗi trang danh sách tự là bản chuẩn của chính nó.
 */
final class CanonicalUrl
{
    public static function for(Request $request): string
    {
        $url = $request->getSchemeAndHttpHost().CanonicalPath::from($request->getPathInfo());
        $page = $request->query('page');

        if (is_string($page) && ctype_digit($page) && (int) $page > 1) {
            $url .= '?page='.(int) $page;
        }

        return $url;
    }
}
