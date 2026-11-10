<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

/** Ai viết bài cũng tải ảnh và sửa alt được; xoá ảnh (có thể đang dùng trong bài) chỉ Biên tập/Admin. */
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

    public function delete(User $user, Media $media): bool
    {
        return $user->isReviewer();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isReviewer();
    }
}
