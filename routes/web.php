<?php

use App\Enums\PageKind;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\RequiredPageController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\CaptureAttribution;
use App\Http\Middleware\IdentifySite;
use Illuminate\Support\Facades\Route;
use Spatie\ResponseCache\Middlewares\CacheResponse;

Route::get('/sitemap.xml', SitemapController::class)->middleware(IdentifySite::class)->name('sitemap');

// CaptureAttribution chạy trước cache: lượt vào trang lấy từ cache vẫn ghi nguồn truy cập.
Route::middleware([IdentifySite::class, CaptureAttribution::class, CacheResponse::class])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get(ArticleController::PATH, ArticleController::class)->name('articles');
    Route::permanentRedirect(ArticleController::OLD_PATH, ArticleController::PATH);
    Route::get('/tim-kiem', SearchController::class)->name('search');
    Route::get('/kien-thuc/{slug}', [PostController::class, 'show'])->name('posts.show');
    Route::get('/danh-muc/{slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/tac-gia/{slug}', [AuthorController::class, 'show'])->name('authors.show');
    Route::get('/san-pham/{slug}', [ProductController::class, 'show'])->name('products.show');

    foreach (PageKind::cases() as $kind) {
        Route::get($kind->path(), [RequiredPageController::class, 'show'])->defaults('kind', $kind->value)->name('pages.'.$kind->value);
    }

    Route::get(QuoteRequestController::PATH, [QuoteRequestController::class, 'create'])->name('quote.create');
    Route::post(QuoteRequestController::PATH, [QuoteRequestController::class, 'store'])->name('quote.store');
    Route::get(QuoteRequestController::PATH.'/da-gui', [QuoteRequestController::class, 'thanks'])->name('quote.thanks');
});

// Mọi domain (kể cả trang quản trị) đều có robots.txt; site lấy từ RedirectToCanonicalUrl.
Route::get('/robots.txt', RobotsController::class)->name('robots');
