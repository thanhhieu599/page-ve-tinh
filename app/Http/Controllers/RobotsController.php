<?php

namespace App\Http\Controllers;

use App\Support\CurrentSite;
use Illuminate\Http\Response;

/**
 * robots.txt theo domain.
 *
 * Site chưa bật index vẫn cho thu thập (không Disallow) nhưng không khai báo sitemap: Google
 * phải vào được trang mới đọc được noindex; chặn bằng Disallow thì URL đã lên Google trước đó
 * vẫn nằm lại dưới dạng URL trơn. Site đã bật index khai báo sitemap của chính nó.
 * Domain không phải site (trang quản trị, IP…) chặn toàn bộ.
 */
class RobotsController extends Controller
{
    public function __invoke(CurrentSite $currentSite): Response
    {
        $site = $currentSite->get();

        $lines = match (true) {
            $site === null => ['User-agent: *', 'Disallow: /'],
            $site->is_indexable => ['User-agent: *', 'Disallow:', '', 'Sitemap: '.$site->url(route('sitemap', absolute: false))],
            default => ['User-agent: *', 'Disallow:'],
        };

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
