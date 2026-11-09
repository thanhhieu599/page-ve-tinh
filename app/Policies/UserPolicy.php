<?php

namespace App\Policies;

use App\Models\User;

/**
 * Chỉ Admin quản lý người dùng, vai trò và việc gán site.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /** Không tự xoá chính mình, tránh mất Admin cuối cùng. */
    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->isNot($model);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
