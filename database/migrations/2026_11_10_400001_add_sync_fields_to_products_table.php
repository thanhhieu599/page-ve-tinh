<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Thời điểm bị ẩn vì ngừng bán trên Sapo. Ẩn tay (CMS) thì để trống.
            $table->timestamp('discontinued_at')->nullable()->after('is_active');
            // Sản phẩm thay thế: URL của sản phẩm bị ẩn chuyển 301 sang đây (không có thì 410).
            $table->foreignId('replacement_product_id')->nullable()->after('discontinued_at')
                ->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replacement_product_id');
            $table->dropColumn('discontinued_at');
        });
    }
};
