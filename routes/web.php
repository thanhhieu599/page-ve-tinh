<?php

use App\Http\Controllers\AuthorController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Middleware\CaptureAttribution;
use App\Http\Middleware\IdentifySite;
use Illuminate\Support\Facades\Route;

Route::middleware([IdentifySite::class, CaptureAttribution::class])->group(function () {
    Route::get('/kien-thuc/{slug}', [PostController::class, 'show'])->name('posts.show');
    Route::get('/danh-muc/{slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/tac-gia/{slug}', [AuthorController::class, 'show'])->name('authors.show');
    Route::get('/san-pham/{slug}', [ProductController::class, 'show'])->name('products.show');

    Route::get(QuoteRequestController::PATH, [QuoteRequestController::class, 'create'])->name('quote.create');
    Route::post(QuoteRequestController::PATH, [QuoteRequestController::class, 'store'])->name('quote.store');
    Route::get(QuoteRequestController::PATH.'/da-gui', [QuoteRequestController::class, 'thanks'])->name('quote.thanks');
});
