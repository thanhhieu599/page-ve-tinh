<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Concerns\BelongsToSite;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

class Post extends Model
{
    use BelongsToSite;

    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use SoftDeletes;

    /** Giới hạn độ dài cả đường dẫn bài viết, ví dụ "/kien-thuc/{slug}". */
    public const MAX_PATH_LENGTH = 75;

    public const PATH_PREFIX = '/kien-thuc/';

    protected $fillable = [
        'slug', 'locale', 'title', 'excerpt', 'content', 'status', 'published_at',
        'seo_title', 'meta_description', 'og_image',
    ];

    protected static function booted(): void
    {
        // Mọi đường lưu bài (trang quản trị, nhập liệu, seeder) đều có slug chuẩn.
        static::saving(function (Post $post): void {
            if (blank($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
        });
    }

    /**
     * Nội dung luôn được lọc HTML trước khi lưu, nên có thể in thẳng ra trang.
     *
     * @return Attribute<string, string>
     */
    protected function content(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => Purifier::clean((string) $value, 'post_content'));
    }

    public function path(): string
    {
        return self::PATH_PREFIX.$this->slug;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'status' => PostStatus::class,
        ];
    }

    /**
     * Bài đã đăng và đã tới giờ đăng.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
