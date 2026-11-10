<?php

namespace App\Models;

use App\Enums\KeywordIntent;
use App\Models\Concerns\BelongsToSite;
use App\Models\Scopes\SiteScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Normalizer;

/**
 * Bản đồ từ khoá: mỗi từ khoá chỉ có một trang đích trên toàn hệ thống, để các trang
 * (cùng hoặc khác site) không tự tranh thứ hạng của nhau.
 */
class KeywordMap extends Model
{
    use BelongsToSite;

    protected $table = 'keyword_map';

    protected $fillable = ['keyword', 'target_url', 'intent'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['intent' => KeywordIntent::class];
    }

    /**
     * Dạng chuẩn để so khớp: Unicode NFC (dấu tiếng Việt dựng sẵn, để "găng" gõ bằng bộ gõ
     * khác nhau vẫn là một), chữ thường, bỏ khoảng trắng thừa.
     */
    public static function normalize(string $keyword): string
    {
        $keyword = Normalizer::normalize($keyword, Normalizer::FORM_C) ?: $keyword;

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $keyword)));
    }

    /** Trang đang giữ từ khoá này, trên mọi site. */
    public static function ownerOf(string $keyword, ?int $exceptId = null): ?self
    {
        return static::withoutGlobalScope(SiteScope::class)
            ->with('site')
            ->where('keyword', self::normalize($keyword))
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->first();
    }

    /** URL đầy đủ của trang đích. */
    public function targetUrl(): string
    {
        return $this->site->url($this->target_url);
    }

    /** @return Attribute<string, string> */
    protected function keyword(): Attribute
    {
        return Attribute::make(set: fn (string $value) => self::normalize($value));
    }
}
