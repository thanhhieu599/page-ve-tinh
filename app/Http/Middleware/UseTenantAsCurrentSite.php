<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Support\CurrentSite;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trong trang quản trị, site đang chọn (tenant của Filament) trở thành site hiện tại,
 * để global scope lọc theo site giống như trên site công khai.
 */
class UseTenantAsCurrentSite
{
    public function __construct(private CurrentSite $currentSite) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Site) {
            $this->currentSite->set($tenant);
        }

        return $next($request);
    }
}
