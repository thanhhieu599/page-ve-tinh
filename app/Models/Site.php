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

    protected $fillable = ['domain', 'name', 'theme', 'settings', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
