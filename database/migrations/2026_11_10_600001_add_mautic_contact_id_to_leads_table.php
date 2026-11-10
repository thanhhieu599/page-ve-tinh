<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('mautic_contact_id')->nullable()->after('synced_to_mautic_at');
            $table->index(['site_id', 'synced_to_mautic_at']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'synced_to_mautic_at']);
            $table->dropColumn('mautic_contact_id');
        });
    }
};
