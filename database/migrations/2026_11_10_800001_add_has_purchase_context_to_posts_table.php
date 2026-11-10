<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Bài có ngữ cảnh mua hàng/báo giá: được link sang trangthietbi.vn (chỉ Biên tập/Admin bật).
            $table->boolean('has_purchase_context')->default(false)->after('author_id');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('has_purchase_context');
        });
    }
};
