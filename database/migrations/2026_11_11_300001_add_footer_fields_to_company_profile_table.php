<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profile', function (Blueprint $table) {
            // Footer dùng chung mọi site: thêm địa chỉ, người liên hệ, mạng xã hội, thanh toán, logo BCT.
            $table->json('addresses')->nullable()->after('region');
            $table->json('contacts')->nullable()->after('addresses');
            $table->json('socials')->nullable()->after('contacts');
            $table->json('payment_methods')->nullable()->after('socials');
            $table->string('bct_badge')->nullable()->after('payment_methods');
            $table->string('bct_url')->nullable()->after('bct_badge');
        });
    }

    public function down(): void
    {
        Schema::table('company_profile', function (Blueprint $table) {
            $table->dropColumn(['addresses', 'contacts', 'socials', 'payment_methods', 'bct_badge', 'bct_url']);
        });
    }
};
