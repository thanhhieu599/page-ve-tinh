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
