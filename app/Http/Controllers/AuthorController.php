<?php

namespace App\Http\Controllers;

use App\Models\Author;
use Illuminate\Contracts\View\View;

class AuthorController extends Controller
{
    public function show(string $slug): View
    {
        $author = Author::query()->where('slug', $slug)->firstOrFail();

        // Global scope của Post giữ danh sách trong site đang xem.
        $posts = $author->posts()
            ->published()
            ->latest('published_at')
            ->paginate(CategoryController::PER_PAGE)
            ->withPath($author->path());

        abort_if($posts->isEmpty() && $posts->currentPage() > 1, 404);

        return view('theme::authors.show', ['author' => $author, 'posts' => $posts]);
    }
}
