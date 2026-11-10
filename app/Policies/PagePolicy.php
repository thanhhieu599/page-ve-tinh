<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * Trang bắt buộc là nội dung pháp lý/thương hiệu: Biên tập và Admin sửa, người viết không.
 * Không thêm/xoá: mỗi site luôn có đúng ba trang, tạo sẵn khi tạo site.
 */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isReviewer();
    }

    public function view(User $user, Page $page): bool
    {
        return $user->isReviewer();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Page $page): bool
    {
        return $user->isReviewer();
    }

    public function delete(User $user, Page $page): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
