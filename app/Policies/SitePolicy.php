<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

/** Thêm, cấu hình site là việc của Admin. Không xoá site trong trang quản trị (mất toàn bộ nội dung). */
class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Site $site): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Site $site): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Site $site): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
