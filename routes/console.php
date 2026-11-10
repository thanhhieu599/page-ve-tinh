<?php

use App\Enums\Role;
use App\Jobs\PullSearchConsoleData;
use App\Models\RequiredPage;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
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

// Kéo số liệu Search Console cho mọi site đã cấu hình property (kể cả site đang tắt), mỗi site một job.
// Lần đầu kết nối một site: chạy một lần với --days=480 để lấy lại lịch sử (Search Console giữ ~16 tháng).
Artisan::command('search-console:pull {--days='.PullSearchConsoleData::DAILY_DAYS.' : Số ngày gần nhất cần kéo}', function () {
    $days = (int) $this->option('days');
    if ($days < 1 || $days > PullSearchConsoleData::MAX_DAYS) {
        $this->error('--days phải từ 1 đến '.PullSearchConsoleData::MAX_DAYS.'.');

        return 1;
    }

    $sites = Site::query()->orderBy('id')->get()->filter(fn (Site $site) => $site->searchConsoleProperty() !== null);

    foreach ($sites as $site) {
        PullSearchConsoleData::dispatch($site->id, $days);
        $this->line("Đã xếp hàng kéo Search Console ({$days} ngày): {$site->domain}");
    }

    return 0;
})->purpose('Kéo số liệu Search Console cho các site đã cấu hình property');

// Hằng ngày 07:10 giờ Việt Nam (cần cron chạy `php artisan schedule:run` mỗi phút và hàng đợi đang chạy).
Schedule::command('search-console:pull')->dailyAt('07:10')->timezone(config('app.display_timezone'));

// Sao lưu (giờ Việt Nam): dọn bản cũ 01:40, sao lưu 02:10, kiểm tra bản mới nhất 08:20.
Schedule::command('backup:clean')->dailyAt('01:40')->timezone(config('app.display_timezone'));
Schedule::command('backup:run')->dailyAt('02:10')->timezone(config('app.display_timezone'));
Schedule::command('backup:monitor')->dailyAt('08:20')->timezone(config('app.display_timezone'));
