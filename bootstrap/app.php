<?php

use App\Http\Middleware\NoindexUnlessIndexable;
use App\Http\Middleware\RedirectToCanonicalUrl;
use App\Http\Middleware\TrustConfiguredProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(TrustProxies::class, TrustConfiguredProxies::class);
        // Chạy sau TrustProxies để biết đúng https khi đứng sau Nginx/Cloudflare.
        // NoindexUnlessIndexable bọc ngoài để gắn noindex cả cho 410 từ bảng redirect.
        $middleware->append(NoindexUnlessIndexable::class);
        $middleware->append(RedirectToCanonicalUrl::class);

        // Form yêu cầu báo giá công khai không dùng CSRF (xem partial quote-form).
        $middleware->preventRequestForgery(except: ['yeu-cau-bao-gia']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
