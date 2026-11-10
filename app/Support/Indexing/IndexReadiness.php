<?php

namespace App\Support\Indexing;

use App\Models\Post;
use App\Models\Scopes\SiteScope;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;

/**
 * Checklist trước khi cho Google index một site (Q27): đủ bài đã đăng, bài có tác giả
 * kèm hồ sơ (E-E-A-T), và có trang công ty, chính sách quyền riêng tư, liên hệ.
 */
final class IndexReadiness
{
    public const MIN_PUBLISHED_POSTS = 20;

    /** @param  list<ReadinessItem>  $items */
    private function __construct(public readonly array $items) {}

    public static function for(Site $site): self
    {
        return new self([
            self::publishedPosts($site),
            self::authorProfiles($site),
            // Trang tĩnh chưa có trong hệ thống; issue #15 sẽ thay bằng kiểm tra thật.
            new ReadinessItem('Trang giới thiệu công ty', false, 'Chưa có (làm ở issue #15)'),
            new ReadinessItem('Chính sách quyền riêng tư', false, 'Chưa có (làm ở issue #15)'),
            new ReadinessItem('Trang liên hệ', false, 'Chưa có (làm ở issue #15)'),
        ]);
    }

    public function isReady(): bool
    {
        foreach ($this->items as $item) {
            if (! $item->passed) {
                return false;
            }
        }

        return true;
    }

    /** @return Builder<Post> */
    private static function published(Site $site): Builder
    {
        return Post::withoutGlobalScope(SiteScope::class)->where('site_id', $site->id)->published();
    }

    private static function publishedPosts(Site $site): ReadinessItem
    {
        $count = self::published($site)->count();
        $passed = $count >= self::MIN_PUBLISHED_POSTS;

        return new ReadinessItem(
            'Ít nhất '.self::MIN_PUBLISHED_POSTS.' bài đã duyệt và đã đăng',
            $passed,
            $passed ? "Đủ {$count} bài" : "Mới có {$count}/".self::MIN_PUBLISHED_POSTS.' bài',
        );
    }

    private static function authorProfiles(Site $site): ReadinessItem
    {
        $withoutProfile = self::published($site)
            ->whereDoesntHave('author', fn (Builder $author) => $author->whereNotNull('bio')->where('bio', '!=', ''))
            ->count();
        $hasAny = self::published($site)->exists();
        $passed = $hasAny && $withoutProfile === 0;

        return new ReadinessItem(
            'Hồ sơ tác giả cho mọi bài đã đăng',
            $passed,
            match (true) {
                ! $hasAny => 'Chưa có bài đã đăng',
                $passed => 'Mọi bài đều có tác giả kèm hồ sơ',
                default => "{$withoutProfile} bài chưa có tác giả kèm hồ sơ giới thiệu",
            },
        );
    }
}
