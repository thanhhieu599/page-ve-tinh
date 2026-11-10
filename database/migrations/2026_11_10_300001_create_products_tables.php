<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            // Thuộc Sapo (CSV/API ghi đè được): tên, SKU, thông số, link mua, giá.
            $table->string('name');
            $table->string('sku', 100);
            $table->json('specs')->nullable();
            $table->string('buy_url')->nullable();
            // Giá tham khảo theo VNĐ, chưa gồm VAT.
            $table->unsignedBigInteger('price')->nullable();
            $table->timestamp('price_updated_at')->nullable();
            // Do CMS quản lý, không bị nhập liệu ghi đè.
            $table->string('slug');
            $table->text('description')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('source', 10)->default('manual');
            $table->timestamps();

            $table->unique(['site_id', 'sku']);
            $table->unique(['site_id', 'slug']);
            $table->index(['site_id', 'price_updated_at']);
        });

        Schema::create('post_product', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->primary(['post_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_product');
        Schema::dropIfExists('products');
    }
};
