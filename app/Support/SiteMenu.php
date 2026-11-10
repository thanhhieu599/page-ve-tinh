<?php

namespace App\Support;

use App\Enums\PageKind;
use App\Models\Category;
use App\Models\Site;

/**
 * Menu chính của site: trang chủ, danh mục gốc đang có bài, trang giới thiệu/liên hệ đã bật.
 * Chỉ link trong site (chính sách liên kết #14), không anchor từ khoá lặp ngoài tên danh mục.
 */
final class SiteMenu
{
    public const MAX_CATEGORIES = 6;

    /** @return list<array{label: string, href: string}> */
    public static function items(Site $site): array
    {
        // Category::liveRoots() lọc theo site đang xem (global scope), cùng site với $site.
        $categories = Category::liveRoots(['name', 'slug'])->take(self::MAX_CATEGORIES)
            ->map(fn (Category $category) => ['label' => $category->name, 'href' => $category->path()])->values()->all();

        $pages = $site->requiredPages()->published()->whereIn('kind', [PageKind::About, PageKind::Contact])->get()
            ->sortBy(fn ($page) => $page->kind->menuOrder())
            ->map(fn ($page) => ['label' => $page->kind->getLabel(), 'href' => $page->path()])->values()->all();

        return [['label' => 'Trang chủ', 'href' => '/'], ...$categories, ...$pages];
    }
}
