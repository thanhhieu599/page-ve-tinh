<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * Lead chứa dữ liệu cá nhân của khách: chỉ Biên tập và Admin xem/cập nhật.
 * Lead chỉ sinh ra từ form trên site, không tạo tay; chỉ Admin xoá.
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isReviewer();
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->isReviewer();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->isReviewer();
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
