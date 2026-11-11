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
 * Host không thuộc site nào đang hoạt động thì trả 404 (chạy thử trên máy: trang hướng dẫn).
 */
class IdentifySite
{
    public function __construct(private CurrentSite $currentSite) {}

    public function handle(Request $request, Closure $next): Response
    {
        // RedirectToCanonicalUrl (chạy trước) thường đã xác định site.
        $site = $this->currentSite->get() ?? Site::findActiveByHost($request->getHost());

        if ($site === null) {
            // Chạy thử trên máy mà mở bằng IP/localhost: chỉ đường tới đúng tên miền thay vì 404 khó hiểu.
            abort_unless(app()->environment('local'), 404);

            return $this->localSiteList($request);
        }

        $this->currentSite->set($site);

        View::replaceNamespace('theme', Theme::path(Theme::resolve($site->theme)));
        View::share('site', $site);

        return $next($request);
    }

    /** Danh sách site và đường dẫn đúng khi chạy thử trên máy (không bao giờ hiện trên máy chủ thật). */
    private function localSiteList(Request $request): Response
    {
        $port = in_array($request->getPort(), [80, 443], true) ? '' : ':'.$request->getPort();
        $origin = fn (string $host) => $request->getScheme().'://'.$host.$port;

        return response()->view('local-sites', [
            'sites' => Site::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'requestedHost' => $request->getHost(),
            'origin' => $origin,
            'adminUrl' => $origin((string) config('sites.admin_domain')).'/admin',
            'adminDomain' => (string) config('sites.admin_domain'),
        ])->header('X-Robots-Tag', Site::NOINDEX);
    }
}
