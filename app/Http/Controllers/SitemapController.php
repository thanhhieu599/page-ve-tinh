<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\RequiredPage;
use App\Support\CurrentSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * sitemap.xml của site đang xem (global scope lọc theo site). Chỉ gồm URL đang sống:
 * bài đã đăng (không nháp, chờ duyệt, hẹn giờ, đã xoá), sản phẩm đang bán, danh mục và
 * tác giả có ít nhất một bài đã đăng (tránh trang rỗng).
 *
 * Chỉ đọc các cột cần cho URL. Một file đủ tới 50.000 URL; vượt mức đó cần tách
 * sitemap index (chưa cần ở quy mô hiện tại).
 */
class SitemapController extends Controller
{
    private const COLUMNS = ['id', 'slug', 'updated_at'];

    public function __invoke(Request $request, CurrentSite $currentSite): Response
    {
        $site = $currentSite->get();
        abort_if($site === null, 404);

        $sitemap = Sitemap::create()->add(Url::create($site->url('/')));
        $add = function (Post|Category|Author|Product|RequiredPage $model) use ($sitemap, $site): void {
            $sitemap->add(Url::create($site->url($model->path()))->setLastModificationDate($model->updated_at));
        };

        Post::query()->published()->latest('published_at')->get(self::COLUMNS)->each($add);

        $hasPublishedPost = fn (Builder $query) => $query->published();

        Category::live(self::COLUMNS)->each($add);

        Author::query()->whereHas('posts', $hasPublishedPost)->get(self::COLUMNS)->each($add);

        Product::query()->active()->get(self::COLUMNS)->each($add);

        RequiredPage::query()->published()->get(['id', 'kind', 'updated_at'])->each($add);

        return $sitemap->toResponse($request);
    }
}
