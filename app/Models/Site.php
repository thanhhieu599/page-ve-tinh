<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một domain vệ tinh. Mỗi ngành hàng là một site.
 */
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected $fillable = ['domain', 'name', 'theme', 'settings', 'is_active', 'requires_expert_review'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
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
