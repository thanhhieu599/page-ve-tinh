<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * sitemap.xml của site đang xem (global scope lọc theo site). Chỉ gồm URL đang sống:
 * bài đã đăng (không nháp, chờ duyệt, hẹn giờ, đã xoá), sản phẩm đang bán, danh mục và
 * tác giả có ít nhất một bài đã đăng (tránh trang rỗng).
 */
class SitemapController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $origin = $request->getSchemeAndHttpHost();
        $sitemap = Sitemap::create();
        $add = function (Post|Category|Author|Product $model) use ($sitemap, $origin): void {
            $sitemap->add(Url::create($origin.$model->path())->setLastModificationDate($model->updated_at));
        };

        Post::query()->published()->latest('published_at')->get()->each($add);

        $hasPublishedPost = fn (Builder $query) => $query->published();

        Category::query()->whereHas('posts', $hasPublishedPost)->get()
            ->flatMap(fn (Category $category) => $category->ancestry())
            ->unique('id')
            ->each($add);

        Author::query()->whereHas('posts', $hasPublishedPost)->get()->each($add);

        Product::query()->active()->get()->each($add);

        return $sitemap->toResponse($request);
    }
}
