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
