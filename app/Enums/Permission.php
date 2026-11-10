<?php

namespace App\Enums;

/**
 * Quyền riêng lẻ, cấp thêm cho từng người ngoài vai trò.
 */
enum Permission: string
{
    /** Được đăng bài trên site yêu cầu duyệt chuyên môn (PPE: tiêu chuẩn, mức bảo vệ). */
    case ApproveExpertContent = 'approve-expert-content';
}
