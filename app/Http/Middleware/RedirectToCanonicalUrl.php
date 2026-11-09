<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use App\Models\Scopes\SiteScope;
use App\Models\Site;
use App\Support\CanonicalPath;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chuyển 301 mọi biến thể URL về một dạng chuẩn duy nhất (https, không www,
 * chữ thường, không "/" cuối, không .html), rồi áp bảng redirect của site.
 * Tất cả gộp trong một lần chuyển hướng.
 */
class RedirectToCanonicalUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        $origin = $this->canonicalScheme($request).'://'.$this->canonicalHost($request);
        $path = CanonicalPath::from($request->getPathInfo());
        $query = (string) $request->server('QUERY_STRING');
        $suffix = $query !== '' ? '?'.$query : '';

        $redirect = $this->findRedirect($request, $path);

        if ($redirect !== null) {
            $redirect->increment('hits');

            abort_if($redirect->code === Redirect::GONE, 410);

            return redirect()->away($origin.$redirect->to_path.$suffix, 301);
        }

        $canonical = $origin.$path.$suffix;

        if ($canonical !== $request->getSchemeAndHttpHost().$request->getRequestUri()) {
            return redirect()->away($canonical, 301);
        }

        return $next($request);
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

    private function findRedirect(Request $request, string $path): ?Redirect
    {
        $site = Site::findActiveByHost($this->bareHost($request));

        if ($site === null) {
            return null;
        }

        return Redirect::withoutGlobalScope(SiteScope::class)
            ->where('site_id', $site->id)
            ->where('from_path', $path)
            ->first();
    }
}
