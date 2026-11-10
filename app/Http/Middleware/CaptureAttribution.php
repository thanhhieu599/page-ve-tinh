<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ghi trang vào đầu tiên và UTM của lần vào đó vào phiên, để lead biết khách đến từ đâu
 * dù gửi form ở trang khác. Chỉ ghi lần đầu của phiên.
 */
class CaptureAttribution
{
    public const SESSION_KEY = 'attribution';

    private const UTM = ['utm_source', 'utm_medium', 'utm_campaign'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->session()->has(self::SESSION_KEY)) {
            $attribution = ['landing_url' => Str::limit($request->getRequestUri(), 490, '')];
            foreach (self::UTM as $key) {
                $value = $request->query($key);
                $attribution[$key] = is_string($value) && $value !== '' ? Str::limit($value, 250, '') : null;
            }

            $request->session()->put(self::SESSION_KEY, $attribution);
        }

        return $next($request);
    }
}
