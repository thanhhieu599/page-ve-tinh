<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Post;
use App\Models\User;

/**
 * Quyền trên bài viết. Việc giới hạn theo site do tenant của trang quản trị và
 * global scope đảm nhận; policy chỉ quyết định theo vai trò.
 */
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function view(User $user, Post $post): bool
    {
        return $user->canAccessAdmin();
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function update(User $user, Post $post): bool
    {
        return $user->canAccessAdmin();
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->canAccessAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->hasAnyRole([Role::Admin->value, Role::Editor->value]);
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasAnyRole([Role::Admin->value, Role::Editor->value]);
    }

    /** Xoá vĩnh viễn không khôi phục được, chỉ Admin. */
    public function forceDelete(User $user, Post $post): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
