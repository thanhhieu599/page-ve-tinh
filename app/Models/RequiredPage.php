<?php

namespace App\Models;

use App\Enums\PageKind;
use App\Models\Concerns\BelongsToSite;
use App\Models\Scopes\SiteScope;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Mews\Purifier\Facades\Purifier;

/**
 * Trang bắt buộc của site (giới thiệu, chính sách quyền riêng tư, liên hệ). Mỗi loại một
 * trang mỗi site; tạo sẵn bản nháp mẫu khi tạo site, biên tập sửa rồi bật hiển thị.
 */
class RequiredPage extends Model
{
    use BelongsToSite;

    // Giữ tên bảng ban đầu (đã có trong migration).
    protected $table = 'pages';

    protected $fillable = ['title', 'content', 'meta_description', 'is_published'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => PageKind::class,
            'is_published' => 'boolean',
        ];
    }

    /** Dấu chỗ trống trong nội dung mẫu ([CẦN BỔ SUNG], [CẦN XÁC NHẬN]): còn thì chưa được bật hiển thị. */
    public const PLACEHOLDER_MARK = '[CẦN ';

    public static function hasPlaceholders(string $content): bool
    {
        return str_contains($content, self::PLACEHOLDER_MARK);
    }

    /** Tạo các trang bắt buộc còn thiếu của site, ở dạng nháp với nội dung mẫu. */
    public static function ensureRequiredFor(Site $site): void
    {
        $existing = $site->requiredPages()->withoutGlobalScope(SiteScope::class)
            ->pluck('kind')->map(fn (PageKind $kind) => $kind->value)->all();

        foreach (PageKind::cases() as $kind) {
            if (in_array($kind->value, $existing, true)) {
                continue;
            }

            $page = new self([
                'title' => $kind->defaultTitle(),
                'content' => view('page-templates.'.$kind->value, ['site' => $site])->render(),
                'is_published' => false,
            ]);
            $page->kind = $kind;
            $page->site_id = $site->id;
            $page->save();
        }
    }

    public function path(): string
    {
        return $this->kind->path();
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /** @return Attribute<string, string> */
    protected function content(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => Purifier::clean((string) $value, 'post_content'));
    }
}
