<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một bản lưu của bài viết (tiêu đề + nội dung), tạo mỗi khi bài được lưu.
 * Thuộc về site thông qua bài viết.
 */
class PostRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['title', 'content', 'user_id'];

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
