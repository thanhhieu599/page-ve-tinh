<?php

use App\Enums\Role;
use App\Models\RequiredPage;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

// Tạo (hoặc nâng quyền) tài khoản Admin, dùng khi dựng server lần đầu.
Artisan::command('admin:create {email} {--name=Admin}', function (string $email) {
    $this->line('Mật khẩu sẽ không hiện khi gõ, đó là bình thường. Hãy TẮT bộ gõ tiếng Việt (Unikey/EVKey) trước khi gõ.');
    $password = (string) $this->secret('Mật khẩu (ít nhất 12 ký tự)');

    if ($password !== (string) $this->secret('Nhập lại mật khẩu')) {
        $this->error('Hai lần nhập mật khẩu không khớp. Chưa tạo tài khoản, hãy chạy lại lệnh.');

        return 1;
    }

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

    $this->info("Đã tạo Admin: {$email}. Đăng nhập tại https://".config('sites.admin_domain').'/admin');

    return 0;
})->purpose('Tạo tài khoản Admin cho trang quản trị');

// Tạo trang bắt buộc còn thiếu (nháp, nội dung mẫu) cho mọi site; không ghi đè trang đã có.
// Chạy một lần khi deploy bản có trang bắt buộc, cho các site tạo từ trước.
Artisan::command('sites:ensure-pages', function () {
    foreach (Site::query()->orderBy('id')->get() as $site) {
        RequiredPage::ensureRequiredFor($site);
        $this->line("Đã kiểm tra trang bắt buộc: {$site->domain}");
    }
})->purpose('Tạo trang giới thiệu, chính sách quyền riêng tư, liên hệ còn thiếu cho các site (dạng nháp)');
