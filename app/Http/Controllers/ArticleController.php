<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Contracts\View\View;

/** Trang "Tin tức": mọi bài đã đăng của site, mới nhất trước (bài vẫn ở /kien-thuc/{slug}). */
class ArticleController extends Controller
{
    public const PATH = '/tin-tuc';

    /** Địa chỉ cũ của trang danh sách bài, chuyển 301 về PATH. */
    public const OLD_PATH = '/kien-thuc';

    public function __invoke(): View
    {
        $posts = Post::query()->published()->with('categories')->latest('published_at')
            ->paginate(CategoryController::PER_PAGE)->withPath(self::PATH);

        abort_if($posts->isEmpty() && $posts->currentPage() > 1, 404);

        return view('theme::articles', ['posts' => $posts]);
    }
}
