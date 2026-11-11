<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Ô danh mục (trang chủ "Mua theo danh mục", trang giới thiệu "Chúng tôi cung cấp gì"):
 * ảnh lấy từ một sản phẩm trong danh mục (kể cả danh mục con), kèm số sản phẩm đang bán.
 */
final class CategoryTiles
{
    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, array{category: Category, count: int, image: ?Product}>
     */
    public static function for(Collection $categories, bool $skipEmpty = true): Collection
    {
        return $categories->map(function (Category $category) {
            $products = fn () => Product::query()->active()->whereIn('category_id', $category->selfAndDescendantIds());

            return [
                'category' => $category,
                'count' => $products()->count(),
                'image' => $products()->whereNotNull('media_id')->with('image')->latest('id')->first(),
            ];
        })->filter(fn (array $tile) => ! $skipEmpty || $tile['count'] > 0)->values();
    }
}
