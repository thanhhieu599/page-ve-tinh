<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Contracts\View\View;

/** Trang chủ site vệ tinh: lời chào hàng, danh mục, sản phẩm tiêu biểu, bài kiến thức mới. */
class HomeController extends Controller
{
    public const LATEST_POSTS = 6;

    public const FEATURED_PRODUCTS = 8;

    public function __invoke(): View
    {
        return view('theme::home', [
            'categories' => Category::live()->whereNull('parent_id')->sortBy('name')->values(),
            'posts' => Post::query()->published()->with('categories')->latest('published_at')->limit(self::LATEST_POSTS)->get(),
            'products' => Product::query()->active()->with('image')->latest('id')->limit(self::FEATURED_PRODUCTS)->get(),
        ]);
    }
}
