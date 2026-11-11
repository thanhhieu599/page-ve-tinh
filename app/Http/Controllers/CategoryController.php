<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class CategoryController extends Controller
{
    public const PER_PAGE = 12;

    /** Sản phẩm hiện trên trang danh mục (gồm danh mục con). */
    public const MAX_PRODUCTS = 100;

    public function show(string $slug): View
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();
        $categoryIds = $category->selfAndDescendantIds();

        $posts = Post::query()
            ->published()
            ->whereHas('categories', fn (Builder $query) => $query->whereKey($categoryIds))
            ->latest('published_at')
            ->paginate(self::PER_PAGE)
            ->withPath($category->path());

        // Trang vượt quá trang cuối không tồn tại (tránh trang rỗng bị index).
        abort_if($posts->isEmpty() && $posts->currentPage() > 1, 404);

        $products = Product::query()
            ->active()
            ->whereIn('category_id', $categoryIds)
            ->with(['image', 'category'])
            ->orderBy('name')
            ->limit(self::MAX_PRODUCTS)
            ->get();

        return view('theme::categories.show', ['category' => $category, 'posts' => $posts, 'products' => $products]);
    }
}
