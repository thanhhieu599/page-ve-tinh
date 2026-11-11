<?php

namespace App\Models;

use App\Enums\PageKind;
use App\Models\Concerns\BelongsToSite;
use App\Models\Scopes\SiteScope;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
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

    protected $fillable = ['title', 'content', 'extras', 'meta_description', 'is_published'];

    public const IMAGE_DIRECTORY = 'page-images';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => PageKind::class,
            'is_published' => 'boolean',
            'extras' => 'array',
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

    /**
     * Danh sách trong khối có cấu trúc (bỏ dòng trống). Ví dụ list('milestones', ['year', 'title']).
     *
     * @param  list<string>  $requiredKeys
     * @return list<array<string, string>>
     */
    public function extraList(string $key, array $requiredKeys): array
    {
        $rows = [];
        foreach (array_values((array) ($this->extras[$key] ?? [])) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $row = array_map(fn ($value) => trim((string) $value), $row);
            if (array_filter($requiredKeys, fn (string $k) => ($row[$k] ?? '') === '') === []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return list<string> giá trị chữ (vd. khách hàng tiêu biểu) */
    public function extraTags(string $key): array
    {
        return array_values(array_filter(array_map(fn ($v) => trim((string) $v), (array) ($this->extras[$key] ?? []))));
    }

    /**
     * Ảnh đã tải lên của trang, kèm kích thước thật (cho width/height, tránh xô lệch).
     *
     * @return list<array{url: string, width: ?int, height: ?int}>
     */
    public function extraImages(string $key): array
    {
        $paths = $this->extras[$key] ?? [];
        $paths = is_string($paths) ? [$paths] : array_values((array) $paths);
        $disk = Storage::disk('public');
        $images = [];
        foreach ($paths as $path) {
            if (! is_string($path) || ! str_starts_with($path, self::IMAGE_DIRECTORY.'/') || ! $disk->exists($path)) {
                continue;
            }
            $size = @getimagesize($disk->path($path));
            $images[] = ['url' => $disk->url($path), 'width' => $size[0] ?? null, 'height' => $size[1] ?? null];
        }

        return $images;
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
