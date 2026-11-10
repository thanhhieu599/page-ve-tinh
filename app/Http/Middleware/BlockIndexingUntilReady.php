<?php

namespace App\Http\Middleware;

use App\Support\CurrentSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Site chưa bật index: mọi phản hồi (kể cả 404, sitemap) mang X-Robots-Tag noindex.
 * Chạy toàn cục để phủ cả URL không khớp route nào.
 */
class BlockIndexingUntilReady
{
    public const DIRECTIVE = 'noindex, nofollow';

    public function __construct(private CurrentSite $currentSite) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $site = $this->currentSite->get();

        if ($site !== null && ! $site->is_indexable) {
            $response->headers->set('X-Robots-Tag', self::DIRECTIVE);
        }

        return $response;
    }
}
