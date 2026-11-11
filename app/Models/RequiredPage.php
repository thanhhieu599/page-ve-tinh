<?php

namespace App\Models;

use App\Enums\PageKind;
use App\Models\Concerns\BelongsToSite;
use App\Models\Scopes\SiteScope;
use App\Support\AboutIcons;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

    /** Trang giới thiệu (grilling 11/11/2026): điều kiện được đăng và giới hạn khối. */
    public const ABOUT_MIN_INTRO_WORDS = 80;

    public const ABOUT_MIN_VALUES = 3;

    public const ABOUT_MAX_VALUES = 4;

    public const ABOUT_MAX_CATEGORIES = 6;

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
            if ($kind === PageKind::About) {
                // Trang giới thiệu dùng ô nhập riêng: đoạn mẫu đầu tiên thành "Về chúng tôi".
                $page->upgradeAbout();
            }
            $page->save();
        }
    }

    /** Chuyển mọi trang giới thiệu cũ sang các khối mới (chạy trong migration). */
    public static function upgradeAboutPages(): void
    {
        self::withoutGlobalScopes()->where('kind', PageKind::About)->each(function (self $page): void {
            $page->upgradeAbout();
            $page->save();
        });
    }

    /**
     * Trang giới thiệu cũ (ô soạn thảo dài + cột mốc + hình ảnh hoạt động) → khối mới:
     * đoạn văn đầu thành "Về chúng tôi", khách hàng dạng chữ thành danh sách tên, cam kết có biểu tượng,
     * bỏ cột mốc và hình ảnh hoạt động (ảnh bị xoá khi lưu), bỏ ô soạn thảo.
     */
    public function upgradeAbout(): void
    {
        $extras = (array) ($this->extras ?? []);

        if (trim((string) ($extras['intro'] ?? '')) === '' && preg_match('#<p>(.*?)</p>#s', (string) $this->content, $first)) {
            $text = trim(html_entity_decode(strip_tags($first[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text !== '' && ! self::hasPlaceholders($text)) {
                $extras['intro'] = $text;
            }
        }

        $extras['clients'] = array_values(array_filter(array_map(
            fn ($client) => is_string($client) ? (trim($client) !== '' ? ['name' => trim($client)] : null) : (is_array($client) ? $client : null),
            (array) ($extras['clients'] ?? []),
        )));
        $extras['values'] = array_map(
            fn ($value) => is_array($value) && ! AboutIcons::has($value['icon'] ?? null) ? ['icon' => 'shield', ...$value] : $value,
            array_values((array) ($extras['values'] ?? [])),
        );
        unset($extras['milestones'], $extras['gallery']);

        $this->extras = $extras;
        $this->content = '';
    }

    /** Chữ một dòng/đoạn trong khối có cấu trúc (bỏ khoảng trắng thừa), trống thì null. */
    public function extraText(string $key): ?string
    {
        $value = $this->extras[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Còn thiếu gì để trang giới thiệu được đăng (rỗng = đủ).
     *
     * @return array<string, string> khoá ô nhập => lời nhắc
     */
    public function aboutMissing(): array
    {
        $missing = [];
        if ($this->extraText('headline') === null) {
            $missing['headline'] = 'Cần câu định vị ở banner đầu trang.';
        }
        $words = count(preg_split('/\s+/u', (string) $this->extraText('intro'), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($words < self::ABOUT_MIN_INTRO_WORDS) {
            $missing['intro'] = 'Đoạn "Về chúng tôi" cần ít nhất '.self::ABOUT_MIN_INTRO_WORDS." chữ (hiện có {$words}).";
        }
        $values = count($this->extraList('values', ['title']));
        if ($values < self::ABOUT_MIN_VALUES) {
            $missing['values'] = 'Cần ít nhất '.self::ABOUT_MIN_VALUES." cam kết \"Vì sao chọn\" (hiện có {$values}).";
        }

        return $missing;
    }

    /** @return Collection<int, Category> danh mục đã chọn cho khối "Chúng tôi cung cấp gì", đúng thứ tự đã chọn */
    public function aboutCategories(): Collection
    {
        $ids = array_slice(array_values(array_filter(array_map('intval', (array) ($this->extras['category_ids'] ?? [])))), 0, self::ABOUT_MAX_CATEGORIES);
        $categories = Category::withoutGlobalScopes()->where('site_id', $this->site_id)->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $categories->get($id))->filter()->values();
    }

    /** @return list<array{name: string, logo: ?array{url: string, width: ?int, height: ?int}}> khách hàng tiêu biểu */
    public function clients(): array
    {
        $clients = [];
        foreach ((array) ($this->extras['clients'] ?? []) as $client) {
            $name = trim((string) (is_array($client) ? ($client['name'] ?? '') : $client));
            if ($name === '') {
                continue;
            }
            $logo = is_array($client) ? $this->image($client['logo'] ?? null) : null;
            $clients[] = ['name' => $name, 'logo' => $logo];
        }

        return $clients;
    }

    /** Mọi ảnh trang đang dùng (để xoá ảnh không còn dùng khi lưu). @return list<string> */
    private static function imagePaths(mixed $extras): array
    {
        $paths = [];
        array_walk_recursive($extras, function ($value) use (&$paths): void {
            if (is_string($value) && str_starts_with($value, self::IMAGE_DIRECTORY.'/')) {
                $paths[] = $value;
            }
        });

        return $paths;
    }

    protected static function booted(): void
    {
        static::saved(function (self $page): void {
            if (! $page->wasChanged('extras')) {
                return;
            }
            $before = json_decode((string) ($page->getRawOriginal('extras') ?? 'null'), true);
            foreach (array_diff(self::imagePaths((array) $before), self::imagePaths((array) $page->extras)) as $unused) {
                Storage::disk('public')->delete($unused);
            }
        });
    }

    /**
     * Danh sách trong khối có cấu trúc (bỏ dòng trống). Ví dụ extraList('stats', ['value', 'label']).
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

        return array_values(array_filter(array_map(fn ($path) => $this->image($path), $paths)));
    }

    /** @return ?array{url: string, width: ?int, height: ?int} ảnh trong thư mục ảnh trang (FileUpload có thể lưu dạng mảng) */
    private function image(mixed $path): ?array
    {
        $path = is_array($path) ? (array_values($path)[0] ?? null) : $path;
        $disk = Storage::disk('public');
        if (! is_string($path) || ! str_starts_with($path, self::IMAGE_DIRECTORY.'/') || ! $disk->exists($path)) {
            return null;
        }
        $size = @getimagesize($disk->path($path));

        return ['url' => $disk->url($path), 'width' => $size[0] ?? null, 'height' => $size[1] ?? null];
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
