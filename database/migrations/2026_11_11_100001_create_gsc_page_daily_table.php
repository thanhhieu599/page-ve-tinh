<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Số liệu Search Console theo trang, theo ngày (kéo tự động hằng ngày).
        Schema::create('gsc_page_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            // URL đầy đủ (có thể dài, kèm query); khoá trùng dùng mã băm để URL dài không đè nhau.
            $table->string('page_url', 2048);
            $table->char('page_hash', 40);
            $table->date('date');
            $table->unsignedInteger('impressions');
            $table->unsignedInteger('clicks');
            $table->decimal('ctr', 7, 4);
            $table->decimal('position', 6, 2);
            $table->timestamps();

            // Chạy lại cùng ngày thì ghi đè, không nhân đôi.
            $table->unique(['site_id', 'page_hash', 'date']);
            $table->index(['site_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_page_daily');
    }
};
