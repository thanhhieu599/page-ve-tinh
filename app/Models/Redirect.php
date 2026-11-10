<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use App\Models\Scopes\SiteScope;
use App\Support\CanonicalPath;
use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Chuyển hướng theo site: 301 sang địa chỉ mới, hoặc 410 cho trang đã gỡ.
 */
class Redirect extends Model
{
    use BelongsToSite;

    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    public const MOVED_PERMANENTLY = 301;

    public const GONE = 410;

    /** Giới hạn số bước khi đi theo chuỗi redirect. */
    private const MAX_CHAIN = 10;

    protected $fillable = ['from_path', 'to_path', 'code'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'code' => 'integer',
            'hits' => 'integer',
        ];
    }

    /**
     * Redirect của site hiện tại khớp đường dẫn (đã chuẩn hoá).
     */
    public static function firstMatching(string $path): ?self
    {
        return static::query()->where('from_path', CanonicalPath::from($path))->first();
    }

    /**
     * Đi theo chuỗi redirect (A → B → C) tới bản ghi cuối cùng, để khách chỉ
     * phải chuyển một lần. Trả null nếu chuỗi tạo vòng lặp.
     */
    public function finalDestination(): ?self
    {
        $current = $this;
        $visited = [$this->from_path];

        for ($step = 0; $step < self::MAX_CHAIN; $step++) {
            if ($current->isGone() || $current->pointsOutside()) {
                return $current;
            }

            $next = static::firstMatching((string) strtok((string) $current->to_path, '?'));

            if ($next === null) {
                return $current;
            }

            if (in_array($next->from_path, $visited, true)) {
                return null;
            }

            $visited[] = $next->from_path;
            $current = $next;
        }

        return null;
    }

    /**
     * Ghi nhận một trang của site đã chuyển từ $oldPath sang $newPath:
     * - URL cũ chuyển 301 sang URL mới;
     * - các redirect đang trỏ về URL cũ được trỏ thẳng sang URL mới (không tạo chuỗi);
     * - redirect nào xuất phát từ URL mới bị xoá, vì URL mới đang có trang thật (không vòng lặp).
     */
    public static function recordMove(int $siteId, string $oldPath, string $newPath): void
    {
        $oldPath = CanonicalPath::from($oldPath);
        $newPath = CanonicalPath::from($newPath);

        if ($oldPath === $newPath) {
            return;
        }

        $forSite = fn () => static::withoutGlobalScope(SiteScope::class)->where('site_id', $siteId);

        $forSite()->where('from_path', $newPath)->delete();
        $forSite()->where('to_path', $oldPath)->update(['to_path' => $newPath]);

        $redirect = $forSite()->firstOrNew(['from_path' => $oldPath]);
        $redirect->site_id = $siteId;
        $redirect->to_path = $newPath;
        $redirect->code = self::MOVED_PERMANENTLY;
        $redirect->save();
    }

    public function isGone(): bool
    {
        return $this->code === self::GONE;
    }

    /** Đích là URL đầy đủ (domain khác), không phải đường dẫn trong site. */
    public function pointsOutside(): bool
    {
        return (bool) preg_match('#^https?://#i', (string) $this->to_path);
    }

    /**
     * Lưu đường dẫn nguồn ở dạng chuẩn để khớp với URL đã chuẩn hoá.
     *
     * @return Attribute<string, string>
     */
    protected function fromPath(): Attribute
    {
        return Attribute::make(set: fn (string $value) => CanonicalPath::from($value));
    }

    /**
     * Đích trong site được lưu ở dạng chuẩn (giữ query nếu có), để không phát sinh
     * thêm một lần chuyển. URL đầy đủ sang domain khác giữ nguyên.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function toPath(): Attribute
    {
        return Attribute::make(set: function (?string $value): ?string {
            if ($value === null || $value === '' || preg_match('#^https?://#i', $value)) {
                return $value === '' ? null : $value;
            }

            [$path, $query] = array_pad(explode('?', $value, 2), 2, null);

            return CanonicalPath::from($path).($query !== null && $query !== '' ? '?'.$query : '');
        });
    }
}
