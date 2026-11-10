<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/** Danh mục định hình cấu trúc site, nên chỉ Biên tập và Admin quản lý. */
class CategoryPolicy
{
    /** Mọi người viết đều cần xem để chọn khi soạn bài; màn hình quản lý do Resource::canAccess() chặn. */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function view(User $user, Category $category): bool
    {
        return $user->canAccessAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isReviewer();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isReviewer();
    }

    /** Xoá danh mục cần duyệt chuyên môn sẽ gỡ bảo vệ khỏi các bài trong đó, nên chỉ Admin. */
    public function delete(User $user, Category $category): bool
    {
        return $category->requires_expert_review ? $user->isAdmin() : $user->isReviewer();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
