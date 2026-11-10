<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

/** Tag là nhãn nhẹ, ai viết bài cũng tạo được; chỉ Biên tập/Admin xoá. */
class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->canAccessAdmin();
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->canAccessAdmin();
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->isReviewer();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isReviewer();
    }
}
