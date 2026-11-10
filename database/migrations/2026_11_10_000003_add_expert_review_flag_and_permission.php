<?php

use App\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission as PermissionModel;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // Site PPE: bài chỉ được đăng bởi người có quyền duyệt chuyên môn.
            $table->boolean('requires_expert_review')->default(false)->after('is_active');
        });

        PermissionModel::findOrCreate(Permission::ApproveExpertContent->value, 'web');
    }

    public function down(): void
    {
        PermissionModel::query()->where('name', Permission::ApproveExpertContent->value)->delete();

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('requires_expert_review');
        });
    }
};
