<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Danh mục bài viết của một site, có thể lồng cha-con.
 */
class Category extends Model
{
    use BelongsToSite;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public const PATH_PREFIX = '/danh-muc/';

    /** Giới hạn độ sâu khi đi ngược lên danh mục cha (phòng dữ liệu lỗi tạo vòng). */
    public const MAX_DEPTH = 10;

    protected $fillable = [
        'parent_id', 'slug', 'locale', 'name', 'description',
        'seo_title', 'meta_description', 'requires_expert_review',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['requires_expert_review' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        // Đổi slug: URL danh mục cũ chuyển 301 sang URL mới.
        static::updated(function (Category $category): void {
            if ($category->wasChanged('slug')) {
                Redirect::recordMove($category->site_id, self::PATH_PREFIX.$category->getOriginal('slug'), $category->path());
            }
        });
    }

    /**
     * Danh mục "đang sống" của site hiện tại: có bài đã đăng hoặc sản phẩm đang bán, kèm mọi
     * danh mục cha (trang danh mục cha liệt kê cả nội dung của con). Dùng cho sitemap, menu, trang chủ.
     *
     * @param  list<string>  $columns
     * @return Collection<int, Category>
     */
    public static function live(array $columns = ['*']): Collection
    {
        $columns = $columns === ['*'] ? $columns : array_values(array_unique([...$columns, 'id', 'parent_id']));
        $byId = static::query()->get($columns)->keyBy('id');
        $included = [];

        $withContent = static::query()
            ->where(fn (Builder $query) => $query
                ->whereHas('posts', fn (Builder $posts) => $posts->published())
                ->orWhereHas('products', fn (Builder $products) => $products->active()))
            ->pluck('id');

        foreach ($withContent as $id) {
            // Giới hạn độ sâu để dữ liệu lỗi (vòng cha–con) không làm treo.
            for ($depth = 0; $id !== null && isset($byId[$id]) && ! isset($included[$id]) && $depth < self::MAX_DEPTH; $depth++) {
                $included[$id] = $byId[$id];
                $id = $byId[$id]->parent_id;
            }
        }

        return collect(array_values($included));
    }

    /**
     * Danh mục gốc đang có bài (cho menu và trang chủ), xếp theo tên.
     *
     * @param  list<string>  $columns
     * @return Collection<int, Category>
     */
    public static function liveRoots(array $columns = ['*']): Collection
    {
        return static::live($columns)->whereNull('parent_id')->sortBy('name')->values();
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /** @return BelongsToMany<Post, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_category');
    }

    /**
     * Id của danh mục này và mọi danh mục con cháu (cùng site).
     *
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        $childrenByParent = static::query()
            ->where('site_id', $this->site_id)
            ->whereNotNull('parent_id')
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = [];
        $queue = [$this->id];

        while ($queue !== []) {
            $id = array_shift($queue);
            if (in_array($id, $ids, true)) {
                continue; // phòng dữ liệu lỡ tạo vòng cha-con
            }
            $ids[] = $id;
            foreach ($childrenByParent->get($id, collect()) as $child) {
                $queue[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * Chuỗi danh mục từ gốc tới danh mục này, dùng cho breadcrumb.
     *
     * @return list<Category>
     */
    public function ancestry(): array
    {
        $chain = [];
        $seenIds = [];
        $current = $this;

        while ($current !== null && ! in_array($current->id, $seenIds, true) && count($chain) < self::MAX_DEPTH) {
            array_unshift($chain, $current);
            $seenIds[] = $current->id;
            $current = $current->parent;
        }

        return $chain;
    }

    /** Danh mục này hoặc một danh mục cha của nó yêu cầu duyệt chuyên môn. */
    public function isUnderExpertReview(): bool
    {
        foreach ($this->ancestry() as $category) {
            if ($category->requires_expert_review) {
                return true;
            }
        }

        return false;
    }

    /**
     * Có danh mục nào (hoặc danh mục cha của nó) trong danh sách yêu cầu duyệt chuyên môn không.
     *
     * @param  array<int|string>  $categoryIds
     */
    public static function anyUnderExpertReview(array $categoryIds): bool
    {
        return static::query()->whereKey($categoryIds)->get()
            ->contains(fn (Category $category) => $category->isUnderExpertReview());
    }

    public function path(): string
    {
        return self::PATH_PREFIX.$this->slug;
    }
}
