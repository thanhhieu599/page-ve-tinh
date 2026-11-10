<?php

namespace Tests\Feature\Ops;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Tests\TestCase;
use ZipArchive;

/**
 * Sao lưu hằng ngày ra kho ngoài máy chủ (disk "backup", chuẩn S3). Test dùng kho giả lập.
 */
class BackupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backup');
        // Ảnh thật trong thư mục ảnh tải lên (đúng nơi cấu hình sao lưu đọc), xoá khi xong test.
        File::ensureDirectoryExists(storage_path('app/public/media/kiem-tra-sao-luu'));
        File::put(storage_path('app/public/media/kiem-tra-sao-luu/960.webp'), 'anh-thu-vien');
        config(['backup.backup.password' => null]); // để test mở được file zip
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/public/media/kiem-tra-sao-luu'));

        parent::tearDown();
    }

    public function test_a_backup_holds_the_database_and_uploaded_media_on_the_offsite_disk(): void
    {
        $this->artisan('backup:run')->assertSuccessful();

        $files = Storage::disk('backup')->allFiles();
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.zip', $files[0]);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('backup')->path($files[0])));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertNotEmpty(preg_grep('#^db-dumps/.+\.sql$#', $names), 'Thiếu bản dump database.');
        $this->assertNotEmpty(preg_grep('#public/media/kiem-tra-sao-luu/960\.webp$#', $names), 'Thiếu ảnh thư viện.');
        $this->assertEmpty(preg_grep('#(^|/)\.env$#', $names), 'Không đưa file .env (chứa mật khẩu) ra kho sao lưu.');
    }

    public function test_with_an_archive_password_every_file_in_the_backup_is_encrypted(): void
    {
        // Như trên máy chủ: mật khẩu có trong .env ngay khi ứng dụng khởi động.
        putenv('BACKUP_ARCHIVE_PASSWORD=mat-khau-thu');
        try {
            $this->refreshApplication();
            Storage::fake('backup');

            $this->artisan('backup:run')->assertSuccessful();
        } finally {
            putenv('BACKUP_ARCHIVE_PASSWORD');
        }

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('backup')->path(Storage::disk('backup')->allFiles()[0])));
        $methods = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (! str_ends_with($stat['name'], '/')) {
                $methods[] = $stat['encryption_method'];
            }
        }
        $zip->close();

        $this->assertNotEmpty($methods);
        $this->assertNotContains(ZipArchive::EM_NONE, $methods, 'Có file trong bản sao lưu không được mã hoá.');
    }

    public function test_a_failed_backup_alerts_the_developer(): void
    {
        Notification::fake();
        config([
            'backup.notifications.mail.to' => 'dev@trangthietbi.vn',
            'database.connections.mysql.password' => 'sai-mat-khau',
        ]);

        $this->artisan('backup:run');

        Notification::assertSentTimes(BackupHasFailedNotification::class, 1);
    }

    public function test_backups_run_clean_and_are_monitored_every_day(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('backup:clean')
            ->expectsOutputToContain('backup:run')
            ->expectsOutputToContain('backup:monitor');
    }

    public function test_retention_keeps_thirty_daily_and_twelve_monthly_backups(): void
    {
        $this->assertSame([
            'keep_all_backups_for_days' => 1,
            'keep_daily_backups_for_days' => 30,
            'keep_weekly_backups_for_weeks' => 0,
            'keep_monthly_backups_for_months' => 12,
            'keep_yearly_backups_for_years' => 0,
        ], array_intersect_key(config('backup.cleanup.default_strategy'), array_flip([
            'keep_all_backups_for_days', 'keep_daily_backups_for_days', 'keep_weekly_backups_for_weeks',
            'keep_monthly_backups_for_months', 'keep_yearly_backups_for_years',
        ])));
    }
}
