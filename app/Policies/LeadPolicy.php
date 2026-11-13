<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * Lead chứa dữ liệu cá nhân của khách. Trang quản trị site: Biên tập và Admin.
 * CRM: Trưởng phòng KD/Admin thấy mọi lead; Nhân viên KD chỉ thấy lead được giao.
 * Nhân viên CRM nhập tay lead từ điện thoại, Zalo, hội chợ; chỉ Admin xoá.
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isReviewer() || $user->canAccessCrm();
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->isReviewer() || $user->isSalesManager() || ($user->canAccessCrm() && $lead->assigned_to === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->canAccessCrm();
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead);
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
