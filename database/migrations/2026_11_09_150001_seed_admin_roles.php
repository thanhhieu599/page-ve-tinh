<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Ba vai trò là dữ liệu bắt buộc của hệ thống, nên tạo qua migration để mọi
 * môi trường (kể cả production) đều có.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web');
        }
    }

    public function down(): void
    {
        RoleModel::query()->whereIn('name', array_column(Role::cases(), 'value'))->delete();
    }
};
