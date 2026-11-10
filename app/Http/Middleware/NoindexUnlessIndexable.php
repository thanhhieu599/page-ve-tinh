<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Support\CurrentSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Site chưa bật index: mọi phản hồi (kể cả 404, 410, sitemap) mang X-Robots-Tag noindex.
 * Đăng ký toàn cục, bọc ngoài RedirectToCanonicalUrl để phủ cả URL không khớp route
 * và URL đã gỡ (410) trong bảng redirect.
 */
class NoindexUnlessIndexable
{
    public function __construct(private CurrentSite $currentSite) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $site = $this->currentSite->get();

        if ($site !== null && ! $site->is_indexable) {
            $response->headers->set('X-Robots-Tag', Site::NOINDEX);
        }

        return $response;
    }
}
