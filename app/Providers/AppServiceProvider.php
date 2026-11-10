<?php

namespace App\Providers;

use App\Models\CompanyProfile;
use App\Support\CurrentSite;
use App\Support\PageCache\ClearPageCache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CompanyProfile::CURRENT, fn (): CompanyProfile => CompanyProfile::stored());
        $this->app->scoped(CurrentSite::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Nội dung đổi → xoá cache toàn trang để khách thấy bản mới ngay.
        ClearPageCache::register();
    }
}
