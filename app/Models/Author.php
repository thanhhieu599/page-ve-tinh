<?php

namespace App\Models;

use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Tác giả bài viết, dùng chung cho mọi site (hồ sơ chuyên môn thống nhất cho E-E-A-T).
 * Danh sách bài của tác giả vẫn lọc theo site hiện tại qua global scope của Post.
 */
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory;

    public const PATH_PREFIX = '/tac-gia/';

    protected $fillable = ['name', 'slug', 'job_title', 'bio', 'expertise', 'avatar', 'linkedin_url'];

    protected static function booted(): void
    {
        static::saving(function (Author $author): void {
            if (blank($author->slug)) {
                $author->slug = Str::slug($author->name);
            }
        });

        // Trang tác giả có trên mọi site, nên URL cũ được chuyển 301 trên mọi site.
        static::updated(function (Author $author): void {
            if ($author->wasChanged('slug')) {
                foreach (Site::query()->pluck('id') as $siteId) {
                    Redirect::recordMove($siteId, self::PATH_PREFIX.$author->getOriginal('slug'), $author->path());
                }
            }
        });
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function path(): string
    {
        return self::PATH_PREFIX.$this->slug;
    }
}
