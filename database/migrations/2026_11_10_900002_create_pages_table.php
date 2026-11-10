<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trang bắt buộc của mỗi site: giới thiệu, chính sách quyền riêng tư, liên hệ.
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('title');
            $table->longText('content');
            $table->string('meta_description', 300)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique(['site_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
