<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Danh mục hiển thị của sản phẩm (dùng chung cây danh mục với bài viết). CMS quản lý,
            // nhập CSV/Sapo không ghi đè.
            $table->foreignId('category_id')->nullable()->after('site_id')->constrained()->nullOnDelete();
            $table->index(['site_id', 'category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'category_id', 'is_active']);
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
