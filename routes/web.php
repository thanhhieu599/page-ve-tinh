<?php

use App\Http\Controllers\AuthorController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductController;
use App\Http\Middleware\IdentifySite;
use Illuminate\Support\Facades\Route;

Route::middleware(IdentifySite::class)->group(function () {
    Route::get('/kien-thuc/{slug}', [PostController::class, 'show'])->name('posts.show');
    Route::get('/danh-muc/{slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/tac-gia/{slug}', [AuthorController::class, 'show'])->name('authors.show');
    Route::get('/san-pham/{slug}', [ProductController::class, 'show'])->name('products.show');
});
