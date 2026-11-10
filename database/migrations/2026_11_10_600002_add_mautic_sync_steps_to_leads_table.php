<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Các bước đồng bộ Mautic đã xong (utm, note, campaign), để lần thử lại không gửi trùng.
            $table->json('mautic_synced_steps')->nullable()->after('mautic_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('mautic_synced_steps');
        });
    }
};
