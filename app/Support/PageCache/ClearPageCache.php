<?php

namespace App\Support\PageCache;

use App\Models\Author;
use App\Models\Category;
use App\Models\CompanyProfile;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Post;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\RequiredPage;
use App\Models\Site;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * Lưu hoặc xoá nội dung hiển thị trên site thì xoá cache toàn trang, để khách thấy bản mới ngay.
 * Xoá toàn bộ (đơn giản, chắc chắn đúng): một bài có thể hiện ở trang chủ, danh mục, tác giả,
 * menu, sitemap… Lượng nội dung hiện tại nhỏ nên cache dựng lại rất nhanh.
 */
final class ClearPageCache
{
    /** @var list<class-string<Model>> */
    public const MODELS = [
        Post::class, Category::class, Tag::class, Author::class, Faq::class, Product::class,
        Media::class, RequiredPage::class, Site::class, CompanyProfile::class, Redirect::class,
    ];

    public static function register(): void
    {
        foreach (self::MODELS as $model) {
            $model::saved(fn () => self::clear());
            $model::deleted(fn () => self::clear());
        }
    }

    public static function clear(): void
    {
        if (config('responsecache.enabled')) {
            ResponseCache::clear();
        }
    }
}
