<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tổng Search Console của cả property theo ngày (khớp màn hình Search Console).
        // Cộng dồn số liệu theo trang cho ra số lớn hơn, vì một lượt tìm có thể hiện nhiều trang.
        Schema::create('gsc_site_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('impressions');
            $table->unsignedInteger('clicks');
            $table->decimal('ctr', 7, 4);
            $table->decimal('position', 6, 2);
            $table->timestamps();

            $table->unique(['site_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_site_daily');
    }
};
