<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

/** Ai viết bài cũng tải ảnh và sửa alt được; xoá ảnh chỉ Biên tập/Admin. */
class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function view(User $user, Media $media): bool
    {
        return $user->canAccessAdmin();
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function update(User $user, Media $media): bool
    {
        return $user->canAccessAdmin();
    }

    /** Ảnh đang dùng trong bài không xoá được, để trang không bị vỡ ảnh. */
    public function delete(User $user, Media $media): bool
    {
        return $user->isReviewer() && ! $media->isUsedInPosts();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isReviewer();
    }
}
