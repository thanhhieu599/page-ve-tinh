<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Support\CategoryTiles;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Trang chủ: danh mục (cột trái) + lời chào hàng, sản phẩm nổi bật, mỗi danh mục gốc một
 * khối sản phẩm (kèm "Xem tất cả"), tin tức mới, lời mời báo giá.
 */
class HomeController extends Controller
{
    public const LATEST_POSTS = 6;

    public const PRODUCTS_PER_SHELF = 10;

    public function __invoke(): View
    {
        $roots = Category::liveRoots();

        return view('theme::home', [
            'featured' => $this->featured(),
            'shelves' => $this->shelves($roots),
            'tiles' => CategoryTiles::for($roots),
            'posts' => Post::query()->published()->with('categories')->latest('published_at')->limit(self::LATEST_POSTS)->get(),
        ]);
    }

    /** Sản phẩm được đánh dấu nổi bật; chưa đánh dấu sản phẩm nào thì lấy sản phẩm mới nhất. */
    private function featured(): Collection
    {
        $query = fn () => Product::query()->active()->with(['image', 'category'])->latest('id')->limit(self::PRODUCTS_PER_SHELF);
        $featured = $query()->where('is_featured', true)->get();

        return $featured->isNotEmpty() ? $featured : $query()->get();
    }

    /**
     * Mỗi danh mục gốc có sản phẩm đang bán (kể cả trong danh mục con) thành một khối.
     *
     * @param  Collection<int, Category>  $roots
     * @return Collection<int, array{category: Category, products: Collection<int, Product>}>
     */
    private function shelves(Collection $roots): Collection
    {
        return $roots->map(fn (Category $root) => [
            'category' => $root,
            'products' => Product::query()->active()->with(['image', 'category'])
                ->whereIn('category_id', $root->selfAndDescendantIds())
                ->latest('id')->limit(self::PRODUCTS_PER_SHELF)->get(),
        ])->filter(fn (array $shelf) => $shelf['products']->isNotEmpty())->values();
    }
}
