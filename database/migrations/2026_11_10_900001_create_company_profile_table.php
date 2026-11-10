<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Thông tin D&T dùng chung mọi site: một dòng duy nhất, Admin sửa trong trang quản trị.
        Schema::create('company_profile', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('url')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('street_address')->nullable();
            $table->string('locality')->nullable();
            $table->string('region')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profile');
    }
};
