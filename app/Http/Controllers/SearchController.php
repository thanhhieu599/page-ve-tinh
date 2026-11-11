<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Tìm sản phẩm đang bán (tên, mã) và bài đã đăng (tiêu đề, tóm tắt) trong site đang xem.
 * So khớp theo collation của database: không phân biệt hoa/thường và có/không dấu.
 * Trang kết quả luôn noindex và không vào cache toàn trang.
 */
class SearchController extends Controller
{
    public const MAX_QUERY = 100;

    public const MAX_PRODUCTS = 24;

    public const MAX_POSTS = 20;

    public function __invoke(Request $request): View
    {
        $query = Str::limit(trim((string) $request->query('q', '')), self::MAX_QUERY, '');
        $like = '%'.addcslashes($query, '%_\\').'%';

        $products = $query === '' ? collect() : Product::query()->active()->with(['image', 'category'])
            ->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like))
            ->orderBy('name')->limit(self::MAX_PRODUCTS)->get();

        $posts = $query === '' ? collect() : Post::query()->published()
            ->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('excerpt', 'like', $like))
            ->latest('published_at')->limit(self::MAX_POSTS)->get();

        return view('theme::search', ['query' => $query, 'products' => $products, 'posts' => $posts]);
    }
}
