<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('company');
            $table->string('email');
            $table->string('phone', 50);
            $table->text('need');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Nguồn: trang gửi form, trang vào đầu tiên của phiên, UTM của lần vào đó.
            $table->string('page_url', 500)->nullable();
            $table->string('landing_url', 500)->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            // Bằng chứng đồng ý xử lý dữ liệu cá nhân.
            $table->text('consent_text');
            $table->timestamp('consent_at');
            $table->string('ip', 45)->nullable();
            // Đã qua kiểm tra chống bot (Turnstile). Gửi khi tắt JavaScript thì false.
            $table->boolean('is_verified')->default(false);
            // Theo dõi bán hàng.
            $table->string('status', 20)->default('new');
            $table->unsignedBigInteger('deal_value')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('synced_to_mautic_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
