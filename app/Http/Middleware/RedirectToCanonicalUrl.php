<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use App\Models\Site;
use App\Support\CanonicalPath;
use App\Support\CurrentSite;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Với request vào domain của một site: chuyển 301 mọi biến thể URL về dạng chuẩn
 * (https, không www, chữ thường, không "/" cuối, không .html) và áp bảng redirect
 * của site, tất cả gộp trong một lần chuyển hướng.
 *
 * Host không phải site (IP, health check…) và request khác GET/HEAD đi thẳng qua.
 */
class RedirectToCanonicalUrl
{
    public function __construct(private CurrentSite $currentSite) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $site = Site::findActiveByHost($this->bareHost($request));

        if ($site === null) {
            return $next($request);
        }

        $this->currentSite->set($site);

        $origin = $this->canonicalScheme($request).'://'.$this->canonicalHost($request);
        $path = CanonicalPath::from($request->getPathInfo());
        $queryString = (string) $request->server('QUERY_STRING');

        $redirect = Redirect::firstMatching($path);

        if ($redirect !== null) {
            $destination = $redirect->finalDestination();

            if ($destination === null) {
                Log::warning('Redirect tạo vòng lặp, bỏ qua.', ['site' => $site->domain, 'path' => $path]);
            } else {
                $redirect->increment('hits');

                abort_if($destination->isGone(), Redirect::GONE);

                $target = $destination->pointsOutside() ? (string) $destination->to_path : $origin.$destination->to_path;

                return redirect()->away($this->appendQuery($target, $queryString), Redirect::MOVED_PERMANENTLY);
            }
        }

        $canonical = $this->appendQuery($origin.$path, $queryString);

        if ($canonical !== $request->getSchemeAndHttpHost().$request->getRequestUri()) {
            return redirect()->away($canonical, Redirect::MOVED_PERMANENTLY);
        }

        return $next($request);
    }

    private function appendQuery(string $url, string $queryString): string
    {
        if ($queryString === '') {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').$queryString;
    }

    private function canonicalScheme(Request $request): string
    {
        return config('sites.force_https') ? 'https' : $request->getScheme();
    }

    private function canonicalHost(Request $request): string
    {
        $port = $request->getPort();
        $isDefaultPort = in_array($port, [80, 443, null], true);

        return $isDefaultPort ? $this->bareHost($request) : $this->bareHost($request).':'.$port;
    }

    private function bareHost(Request $request): string
    {
        return (string) preg_replace('/^www\./', '', $request->getHost());
    }
}
