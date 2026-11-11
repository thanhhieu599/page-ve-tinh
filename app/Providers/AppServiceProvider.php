<?php

namespace App\Providers;

use App\Http\Middleware\RecordUploadFailure;
use App\Models\CompanyProfile;
use App\Support\CurrentSite;
use App\Support\PageCache\ClearPageCache;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\FileUploadController;

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
        // Tải tệp lên trang quản trị: ghi lại lý do khi lỗi (bọc ngoài cả nhóm "web" để bắt cả lỗi 419/phiên).
        FileUploadController::$defaultMiddleware = [RecordUploadFailure::class, 'web'];

        // Nội dung đổi → xoá cache toàn trang để khách thấy bản mới ngay.
        ClearPageCache::register();
    }
}
