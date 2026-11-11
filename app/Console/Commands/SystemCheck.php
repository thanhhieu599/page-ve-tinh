<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Kiểm tra máy chạy có đủ điều kiện cho trang quản trị (nhất là tải ảnh lên) và site.
 * Dùng khi gặp lỗi "tải lên không thành công" trên máy Windows/XAMPP/Laragon.
 */
class SystemCheck extends Command
{
    protected $signature = 'he-thong:kiem-tra';

    protected $description = 'Kiểm tra PHP extension, thư mục tạm upload, giới hạn dung lượng, storage:link';

    /** Extension cần có => để làm gì. */
    private const EXTENSIONS = [
        'fileinfo' => 'nhận dạng loại tệp khi tải ảnh lên (thiếu: lỗi "tải lên không thành công")',
        'gd' => 'tạo ảnh thumbnail, đọc kích thước ảnh',
        'mbstring' => 'xử lý tiếng Việt',
        'intl' => 'định dạng số, ngày tiếng Việt',
        'pdo_mysql' => 'kết nối MySQL/MariaDB',
        'zip' => 'sao lưu',
        'openssl' => 'Search Console, gửi mail',
        'curl' => 'Mautic, Search Console',
    ];

    public function handle(): int
    {
        $rows = [];
        $failed = 0;
        $add = function (string $item, bool $ok, string $detail, string $fix) use (&$rows, &$failed): void {
            $failed += $ok ? 0 : 1;
            $rows[] = [$ok ? 'OK' : 'LỖI', $item, $detail, $ok ? '' : $fix];
        };

        foreach (self::EXTENSIONS as $extension => $purpose) {
            $add("PHP extension {$extension}", extension_loaded($extension), $purpose,
                "Mở php.ini, bỏ dấu ; trước dòng extension={$extension}, lưu rồi chạy lại php artisan serve");
        }

        $tmp = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        $add('Thư mục tạm upload', is_dir($tmp) && is_writable($tmp), $tmp,
            'Tạo thư mục (vd. C:\\php\\tmp), đặt upload_tmp_dir="C:\\php\\tmp" trong php.ini');

        $add('file_uploads', (bool) ini_get('file_uploads'), 'cho phép tải tệp lên', 'Đặt file_uploads=On trong php.ini');
        $add('upload_max_filesize', $this->bytes((string) ini_get('upload_max_filesize')) >= 10 * 1024 ** 2,
            (string) ini_get('upload_max_filesize'), 'Đặt upload_max_filesize=20M trong php.ini');
        $add('post_max_size', $this->bytes((string) ini_get('post_max_size')) >= 12 * 1024 ** 2,
            (string) ini_get('post_max_size'), 'Đặt post_max_size=25M trong php.ini');

        foreach (['storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $add("Ghi được {$dir}", is_writable(base_path($dir)), base_path($dir), 'Cấp quyền ghi cho thư mục này');
        }

        $add('storage:link (hiện ảnh đã tải lên)', file_exists(public_path('storage')), public_path('storage'), 'Chạy: php artisan storage:link');

        $this->table(['', 'Mục', 'Chi tiết', 'Cách sửa'], $rows);
        $this->line('php.ini đang dùng: '.(php_ini_loaded_file() ?: '(chưa có – sao chép php.ini-development thành php.ini)'));

        if ($failed > 0) {
            $this->error("{$failed} mục cần sửa. Sửa xong, tắt và chạy lại php artisan serve.");
        } else {
            $this->info('Máy đã đủ điều kiện. Nếu vẫn lỗi tải ảnh, xem cuối file storage/logs/laravel.log.');
        }

        // Lệnh chỉ để chẩn đoán, không chặn deploy.
        return self::SUCCESS;
    }

    private function bytes(string $value): int
    {
        $number = (int) $value;

        return match (strtoupper(substr(trim($value), -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }
}
