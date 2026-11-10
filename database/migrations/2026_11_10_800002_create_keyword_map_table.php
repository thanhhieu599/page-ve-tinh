<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            // Lưu dạng đã chuẩn hoá (chữ thường, một khoảng trắng). So khớp nhị phân để
            // "găng tay" và "gang tay" là hai từ khoá khác nhau (collation unicode_ci coi là trùng).
            $table->string('keyword', 191)->collation('utf8mb4_bin');
            $table->string('target_url', 255);
            $table->string('intent', 20);
            $table->timestamps();

            // Mỗi từ khoá chỉ có một trang đích trên toàn hệ thống.
            $table->unique('keyword');
            $table->index(['site_id', 'target_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_map');
    }
};
