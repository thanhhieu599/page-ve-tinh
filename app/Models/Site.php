<?php

namespace App\Models;

use App\Support\Indexing\IndexReadiness;
use Database\Factories\SiteFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một domain vệ tinh. Mỗi ngành hàng là một site.
 */
class Site extends Model
{
    /** Chỉ thị robots cho site chưa bật index. */
    public const NOINDEX = 'noindex, nofollow';

    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected $fillable = ['domain', 'name', 'theme', 'settings', 'is_active', 'requires_expert_review'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'is_indexable' => 'boolean',
            'requires_expert_review' => 'boolean',
        ];
    }

    /** Mã GA4 trong cấu hình site, chỉ nhận đúng dạng G-XXXX để không chèn được mã lạ vào trang. */
    public function ga4Id(): ?string
    {
        $id = $this->settings['ga4_id'] ?? null;

        return is_string($id) && preg_match('/^G-[A-Z0-9]{4,20}$/', $id) ? $id : null;
    }

    /** Campaign Mautic nhận lead của site (settings.mautic_campaign_id). */
    public function mauticCampaignId(): ?int
    {
        $id = $this->settings['mautic_campaign_id'] ?? null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /** Mã ngành gắn tag Mautic `nganh-<mã>` (settings.industry_tag), chỉ chữ thường, số, gạch nối. */
    public function industryTag(): ?string
    {
        $tag = $this->settings['industry_tag'] ?? null;

        return is_string($tag) && preg_match('/^[a-z0-9-]{1,50}$/', $tag) ? $tag : null;
    }

    /**
     * URL tuyệt đối trên domain của site (https khi FORCE_HTTPS bật, như production).
     * Dùng cho canonical, sitemap, robots.txt.
     */
    public function url(string $path): string
    {
        return (config('sites.force_https') ? 'https' : 'http').'://'.$this->domain.$path;
    }

    /**
     * Địa chỉ tuyệt đối cho một URL công khai: giữ nguyên nếu đã tuyệt đối (vd. ảnh trên CDN),
     * còn đường dẫn tương đối (/storage/...) thì ghép domain của site.
     */
    public function absoluteUrl(string $url): string
    {
        return preg_match('#^https?://#i', $url) === 1 ? $url : $this->url($url);
    }

    /** Cho Google index site. Chỉ được khi site đạt checklist (IndexReadiness). */
    public function enableIndexing(): void
    {
        if (! IndexReadiness::for($this)->isReady()) {
            throw new DomainException('Site chưa đạt đủ điều kiện mở index.');
        }

        $this->forceFill(['is_indexable' => true])->save();
    }

    public function disableIndexing(): void
    {
        $this->forceFill(['is_indexable' => false])->save();
    }

    public static function findActiveByHost(string $host): ?self
    {
        return static::query()
            ->where('domain', strtolower($host))
            ->where('is_active', true)
            ->first();
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
