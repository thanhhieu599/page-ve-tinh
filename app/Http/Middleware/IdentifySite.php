<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Support\CurrentSite;
use App\Support\Theme;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác định site từ HTTP Host và nạp thư mục theme của site đó.
 * Host không thuộc site nào đang hoạt động thì trả 404.
 */
class IdentifySite
{
    public function __construct(private CurrentSite $currentSite) {}

    public function handle(Request $request, Closure $next): Response
    {
        // RedirectToCanonicalUrl (chạy trước) thường đã xác định site.
        $site = $this->currentSite->get() ?? Site::findActiveByHost($request->getHost());

        abort_if($site === null, 404);

        $this->currentSite->set($site);

        View::replaceNamespace('theme', Theme::path($site->theme));
        View::share('site', $site);

        return $next($request);
    }
}
