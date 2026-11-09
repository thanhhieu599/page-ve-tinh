<?php

use App\Http\Controllers\PostController;
use App\Http\Middleware\IdentifySite;
use Illuminate\Support\Facades\Route;

Route::middleware(IdentifySite::class)->group(function () {
    Route::get('/kien-thuc/{slug}', [PostController::class, 'show'])->name('posts.show');
});
