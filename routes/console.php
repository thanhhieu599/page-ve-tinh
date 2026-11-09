<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

// Tạo (hoặc nâng quyền) tài khoản Admin, dùng khi dựng server lần đầu.
Artisan::command('admin:create {email} {--name=Admin}', function (string $email) {
    $password = (string) $this->secret('Mật khẩu (ít nhất 12 ký tự)');

    $validator = Validator::make(
        ['email' => $email, 'password' => $password],
        ['email' => ['required', 'email'], 'password' => ['required', 'min:12']],
    );

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $user = User::query()->updateOrCreate(
        ['email' => $email],
        ['name' => (string) $this->option('name'), 'password' => $password],
    );
    $user->assignRole(Role::Admin->value);

    $this->info("Đã tạo Admin: {$email}");

    return 0;
})->purpose('Tạo tài khoản Admin cho trang quản trị');
