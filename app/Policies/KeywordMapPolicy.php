<?php

namespace App\Policies;

use App\Models\KeywordMap;
use App\Models\User;

/**
 * Bản đồ từ khoá là quyết định SEO: Biên tập và Admin gán từ khoá mới, người viết không.
 * Chuyển từ khoá sang trang khác (sửa trang đích, ghi đè, xoá để gán lại) chỉ Admin.
 */
class KeywordMapPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isReviewer();
    }

    public function view(User $user, KeywordMap $keywordMap): bool
    {
        return $user->isReviewer();
    }

    public function create(User $user): bool
    {
        return $user->isReviewer();
    }

    public function update(User $user, KeywordMap $keywordMap): bool
    {
        return $user->isReviewer();
    }

    /** Đổi trang đích của từ khoá đã gán, hoặc ghi đè trang đang giữ từ khoá. */
    public function reassign(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, KeywordMap $keywordMap): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
