<?php

namespace App\Http\Controllers;

use App\Support\CurrentSite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * robots.txt theo domain. Site chưa bật index (và mọi domain không phải site, như trang
 * quản trị) chặn toàn bộ; site đã bật index cho thu thập và khai báo sitemap của chính nó.
 */
class RobotsController extends Controller
{
    public function __invoke(Request $request, CurrentSite $currentSite): Response
    {
        $site = $currentSite->get();

        $lines = $site?->is_indexable
            ? [
                'User-agent: *',
                'Disallow:',
                '',
                'Sitemap: '.$request->getSchemeAndHttpHost().'/sitemap.xml',
            ]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
