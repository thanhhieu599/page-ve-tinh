<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Concerns\BelongsToSite;
use Carbon\CarbonInterface;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    // status, published_at, review_note chỉ đổi qua các bước duyệt bên dưới.
    protected $fillable = [
        'slug', 'locale', 'title', 'excerpt', 'content',
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

        // Đổi slug của bài đã đăng: URL cũ tự chuyển 301 sang URL mới để không mất traffic.
        static::updated(function (Post $post): void {
            if ($post->wasChanged('slug') && $post->getOriginal('status') === PostStatus::Published) {
                Redirect::recordMove($post->site_id, self::PATH_PREFIX.$post->getOriginal('slug'), $post->path());
            }
        });

        // Lưu lịch sử mỗi khi tiêu đề hoặc nội dung đổi.
        static::saved(function (Post $post): void {
            if ($post->wasRecentlyCreated || $post->wasChanged(['title', 'content'])) {
                $post->revisions()->create([
                    'title' => $post->title,
                    'content' => $post->content,
                    'user_id' => auth()->id(),
                ]);
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

    /** @return HasMany<PostRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class);
    }

    /** Đưa tiêu đề và nội dung về một bản cũ. Việc khôi phục cũng thành một bản mới trong lịch sử. */
    public function restoreRevision(PostRevision $revision): void
    {
        $this->title = $revision->title;
        $this->content = $revision->content;
        $this->save();
    }

    /** Người viết gửi bài nháp cho biên tập duyệt. */
    public function submitForReview(): void
    {
        $this->status = PostStatus::Pending;
        $this->review_note = null;
        $this->save();
    }

    /**
     * Biên tập duyệt và đăng. Thời điểm trong tương lai = hẹn giờ đăng.
     */
    public function publish(?CarbonInterface $at = null): void
    {
        $this->status = PostStatus::Published;
        $this->published_at = $at ?? now();
        $this->review_note = null;
        $this->save();
    }

    /** Biên tập trả bài về nháp (hoặc gỡ bài đã đăng), kèm ghi chú cho người viết. */
    public function returnToDraft(?string $note = null): void
    {
        $this->status = PostStatus::Draft;
        $this->review_note = $note;
        $this->save();
    }

    public function isScheduled(): bool
    {
        return $this->status === PostStatus::Published && $this->published_at?->isFuture() === true;
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
