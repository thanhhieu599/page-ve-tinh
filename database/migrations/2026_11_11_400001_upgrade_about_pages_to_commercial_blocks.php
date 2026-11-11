<?php

use App\Models\RequiredPage;
use Illuminate\Database\Migrations\Migration;

/**
 * Trang giới thiệu chuyển sang khối kiểu web thương mại (grilling 11/11/2026):
 * đoạn đầu của nội dung cũ thành "Về chúng tôi", bỏ ô soạn thảo, cột mốc, hình ảnh hoạt động (xoá ảnh).
 */
return new class extends Migration
{
    public function up(): void
    {
        RequiredPage::upgradeAboutPages();
    }

    public function down(): void
    {
        // Không khôi phục: nội dung cũ chỉ là bản mẫu, ảnh đã xoá.
    }
};
