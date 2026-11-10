<?php

namespace App\Policies;

use App\Models\Author;
use App\Models\User;

/** Hồ sơ tác giả hiện công khai trên mọi site, nên chỉ Biên tập và Admin quản lý. */
class AuthorPolicy
{
    /** Mọi người viết đều cần xem để chọn khi soạn bài; màn hình quản lý do Resource::canAccess() chặn. */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function view(User $user, Author $author): bool
    {
        return $user->canAccessAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isReviewer();
    }

    public function update(User $user, Author $author): bool
    {
        return $user->isReviewer();
    }

    public function delete(User $user, Author $author): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
