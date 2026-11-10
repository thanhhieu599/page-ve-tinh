<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Support\CurrentSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $sitemap = Sitemap::create();
        $add = function (Post|Category|Author|Product $model) use ($sitemap, $site): void {
            $sitemap->add(Url::create($site->url($model->path()))->setLastModificationDate($model->updated_at));
        };

        Post::query()->published()->latest('published_at')->get(self::COLUMNS)->each($add);

        $hasPublishedPost = fn (Builder $query) => $query->published();

        self::withAncestors(
            Category::query()->get([...self::COLUMNS, 'parent_id']),
            Category::query()->whereHas('posts', $hasPublishedPost)->pluck('id'),
        )->each($add);

        Author::query()->whereHas('posts', $hasPublishedPost)->get(self::COLUMNS)->each($add);

        Product::query()->active()->get(self::COLUMNS)->each($add);

        return $sitemap->toResponse($request);
    }

    /**
     * Danh mục có bài và mọi danh mục cha của chúng (trang danh mục cha liệt kê cả bài của con).
     *
     * @param  Collection<int, Category>  $all
     * @param  Collection<int, int>  $withPostIds
     * @return Collection<int, Category>
     */
    private static function withAncestors(Collection $all, Collection $withPostIds): Collection
    {
        $byId = $all->keyBy('id');
        $included = [];

        foreach ($withPostIds as $id) {
            // Giới hạn độ sâu để dữ liệu lỗi (vòng cha–con) không làm treo.
            for ($depth = 0; $id !== null && isset($byId[$id]) && ! isset($included[$id]) && $depth < Category::MAX_DEPTH; $depth++) {
                $included[$id] = $byId[$id];
                $id = $byId[$id]->parent_id;
            }
        }

        return collect(array_values($included));
    }
}
