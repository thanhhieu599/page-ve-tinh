<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Contracts\View\View;

/** Trang "Kiến thức": mọi bài đã đăng của site, mới nhất trước. */
class ArticleController extends Controller
{
    public const PATH = '/kien-thuc';

    public function __invoke(): View
    {
        $posts = Post::query()->published()->latest('published_at')
            ->paginate(CategoryController::PER_PAGE)->withPath(self::PATH);

        abort_if($posts->isEmpty() && $posts->currentPage() > 1, 404);

        return view('theme::articles', ['posts' => $posts]);
    }
}
