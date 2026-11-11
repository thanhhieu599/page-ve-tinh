<?php

namespace App\Support;

use App\Enums\PageKind;
use App\Http\Controllers\ArticleController;
use App\Models\Category;
use App\Models\KeywordMap;
use App\Models\Site;

/**
 * Menu của site: cây "Danh mục sản phẩm" (danh mục gốc đang có nội dung + danh mục con) và
 * menu trang (trang chủ, giới thiệu, kiến thức, liên hệ). Chỉ link trong site (chính sách liên kết #14).
 */
final class SiteMenu
{
    public const MAX_ROOTS = 12;

    /**
     * Danh mục gốc kèm danh mục con đang có nội dung, theo site đang xem (global scope).
     *
     * @return list<array{label: string, href: string, children: list<array{label: string, href: string}>}>
     */
    public static function categoryTree(): array
    {
        $live = Category::live(['name', 'slug']);
        $children = $live->whereNotNull('parent_id')->sortBy('name')->groupBy('parent_id');

        return $live->whereNull('parent_id')->sortBy('name')->take(self::MAX_ROOTS)
            ->map(fn (Category $root) => [
                'label' => $root->name,
                'href' => $root->path(),
                'children' => $children->get($root->id, collect())
                    ->map(fn (Category $child) => ['label' => $child->name, 'href' => $child->path()])->values()->all(),
            ])->values()->all();
    }

    /**
     * Danh mục ở footer: danh mục gốc đang có nội dung, bỏ danh mục có tên trùng từ khoá chính trong
     * bản đồ từ khoá (footer lặp trên mọi trang không được thành anchor từ khoá – chính sách #14).
     *
     * @return list<array{label: string, href: string}>
     */
    public static function footerCategories(): array
    {
        return array_values(array_filter(
            array_map(fn (array $root) => ['label' => $root['label'], 'href' => $root['href']], self::categoryTree()),
            fn (array $item) => KeywordMap::ownerOf($item['label']) === null,
        ));
    }

    /** @return list<array{label: string, href: string}> trang chính sách/giới thiệu đã bật, theo thứ tự */
    public static function policyPages(Site $site): array
    {
        return $site->requiredPages()->published()->get()
            ->sortBy(fn ($page) => $page->kind->menuOrder())
            ->map(fn ($page) => ['label' => $page->kind->getLabel(), 'href' => $page->path()])->values()->all();
    }

    /** @return list<array{label: string, href: string}> */
    public static function pages(Site $site): array
    {
        $published = $site->requiredPages()->published()->pluck('kind')->all();
        $has = fn (PageKind $kind) => in_array($kind, $published, true);

        return array_values(array_filter([
            ['label' => 'Trang chủ', 'href' => '/'],
            $has(PageKind::About) ? ['label' => PageKind::About->getLabel(), 'href' => PageKind::About->path()] : null,
            ['label' => 'Kiến thức', 'href' => ArticleController::PATH],
            $has(PageKind::Contact) ? ['label' => PageKind::Contact->getLabel(), 'href' => PageKind::Contact->path()] : null,
        ]));
    }
}
