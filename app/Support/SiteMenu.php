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
        $categories = Category::live(['name', 'slug'])->whereNull('parent_id')->sortBy('name')->take(self::MAX_CATEGORIES)
            ->map(fn (Category $category) => ['label' => $category->name, 'href' => $category->path()])->values()->all();

        $pages = $site->requiredPages()->published()->whereIn('kind', [PageKind::About, PageKind::Contact])->get()
            ->sortBy(fn ($page) => $page->kind === PageKind::About ? 0 : 1)
            ->map(fn ($page) => ['label' => $page->kind->getLabel(), 'href' => $page->path()])->values()->all();

        return [['label' => 'Trang chủ', 'href' => '/'], ...$categories, ...$pages];
    }
}
