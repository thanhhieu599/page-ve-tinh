<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lượt bấm từ site vệ tinh sang sản phẩm trên web chính trangthietbi.vn (đo ROI từng site, trang, sản phẩm).
 * Mỗi lượt một dòng; báo cáo cộng theo khoảng ngày.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_path', 300)->nullable();
            $table->string('placement', 20);
            $table->timestamp('clicked_at')->useCurrent();

            $table->index(['site_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_clicks');
    }
};
