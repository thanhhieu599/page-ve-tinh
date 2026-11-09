<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Tin các proxy khai báo trong config('sites.trusted_proxies') (Nginx, Cloudflare)
 * để đọc đúng https và IP khách. Đọc từ config nên vẫn đúng sau `config:cache`.
 */
class TrustConfiguredProxies extends TrustProxies
{
    /** @return array<int, string>|string|null */
    protected function proxies()
    {
        $proxies = config('sites.trusted_proxies');

        return match (true) {
            $proxies === [] => null,
            $proxies === ['*'] => '*',
            default => $proxies,
        };
    }
}
