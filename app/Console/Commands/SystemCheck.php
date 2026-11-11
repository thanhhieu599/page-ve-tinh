<?php

namespace App\Console\Commands;

use App\Http\Middleware\RecordUploadFailure;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Throwable;

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

        [$ok, $detail] = $this->tryTemporaryUpload();
        $add('Thử lưu ảnh tạm như khi tải lên', $ok, $detail, 'Xem lỗi ở cột Chi tiết; thường do thiếu fileinfo hoặc không ghi được storage/app');

        $this->table(['', 'Mục', 'Chi tiết', 'Cách sửa'], $rows);
        $this->reportLastFailure();
        $this->line('php.ini đang dùng: '.(php_ini_loaded_file() ?: '(chưa có – sao chép php.ini-development thành php.ini)'));

        if ($failed > 0) {
            $this->error("{$failed} mục cần sửa. Sửa xong, tắt và chạy lại php artisan serve.");
        } else {
            $this->info('Máy đã đủ điều kiện. Nếu vẫn lỗi tải ảnh, xem cuối file storage/logs/laravel.log.');
        }

        // Lệnh chỉ để chẩn đoán, không chặn deploy.
        return self::SUCCESS;
    }

    /** Chạy đúng các bước Livewire làm với một ảnh tải lên: kiểm tra, đặt tên theo loại tệp, lưu vào thư mục tạm. */
    private function tryTemporaryUpload(): array
    {
        $source = tempnam(sys_get_temp_dir(), 'kt');
        try {
            // Ảnh PNG 1×1.
            file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
            $file = new UploadedFile($source, 'kiem-tra.png', 'image/png', null, true);
            Validator::make(['files' => [$file]], ['files.*' => FileUploadConfiguration::rules()])->validate();
            $path = FileUploadConfiguration::storeTemporaryFile($file, FileUploadConfiguration::disk());
            Storage::disk(FileUploadConfiguration::disk())->delete($path);

            return [true, 'lưu được vào '.FileUploadConfiguration::disk().':'.FileUploadConfiguration::path()];
        } catch (Throwable $e) {
            return [false, $e->getMessage()];
        } finally {
            @unlink($source);
        }
    }

    private function reportLastFailure(): void
    {
        $file = storage_path(RecordUploadFailure::FILE);
        $entry = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($entry)) {
            return;
        }

        $this->newLine();
        $this->warn("Lần tải lên lỗi gần nhất ({$entry['at']}): HTTP {$entry['status']}");
        $this->line('  Chi tiết: '.($entry['detail'] ?: '(không có)'));
        $this->line('  Gợi ý: '.match (true) {
            in_array(UPLOAD_ERR_INI_SIZE, $entry['php_upload_errors'] ?? [], true) => 'tệp vượt upload_max_filesize trong php.ini',
            in_array(UPLOAD_ERR_NO_TMP_DIR, $entry['php_upload_errors'] ?? [], true) => 'PHP không có thư mục tạm: đặt upload_tmp_dir trong php.ini',
            in_array(UPLOAD_ERR_CANT_WRITE, $entry['php_upload_errors'] ?? [], true) => 'PHP không ghi được thư mục tạm: kiểm tra quyền thư mục upload_tmp_dir',
            $entry['status'] === 401 => 'link tải lên hết hạn hoặc sai chữ ký: tải lại trang quản trị (F5) rồi thử lại; nếu vẫn lỗi, kiểm tra giờ hệ thống của máy',
            $entry['status'] === 419 => 'phiên đăng nhập hết hạn: tải lại trang (F5), đăng nhập lại',
            $entry['status'] === 413 => 'tệp quá lớn: tăng post_max_size/upload_max_filesize',
            $entry['status'] === 422 => 'tệp không hợp lệ hoặc vượt giới hạn: xem Chi tiết',
            $entry['status'] === 429 => 'tải lên quá nhiều trong 1 phút: chờ rồi thử lại',
            default => 'xem Chi tiết ở trên và cuối storage/logs/laravel.log',
        });
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
