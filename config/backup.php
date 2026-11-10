<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

/*
 * Sao lưu hằng ngày (spatie/laravel-backup) ra kho ngoài máy chủ: disk "backup" (chuẩn S3:
 * Cloudflare R2, Amazon S3, Backblaze…), cấu hình bằng biến BACKUP_S3_* trong .env.
 * Gồm database và ảnh tải lên (storage/app/public). Không gồm .env (giữ riêng, an toàn hơn).
 * Lịch chạy: routes/console.php. Hướng dẫn khôi phục: docs/van-hanh.md.
 */
return [

    'backup' => [
        'name' => env('BACKUP_NAME', 'page-ve-tinh'),

        'source' => [
            'files' => [
                'include' => [
                    storage_path('app/public'),
                ],
                'exclude' => [],
                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => storage_path('app'),
            ],
            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        'database_dump_compressor' => null,
        'database_dump_file_timestamp_format' => null,
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => '',
            'disks' => ['backup'],
            'continue_on_failure' => false,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        // Mật khẩu mã hoá file sao lưu (AES-256). Bắt buộc đặt trên production, lưu ở nơi an toàn
        // ngoài máy chủ: mất mật khẩu là không mở được bản sao lưu.
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',

        'verify_backup' => true,
        'tries' => 2,
        'retry_delay' => 60,
    ],

    // Chỉ báo khi có vấn đề (không gửi email mỗi lần sao lưu thành công).
    'notifications' => [
        'notifications' => [
            BackupHasFailedNotification::class => ['mail'],
            UnhealthyBackupWasFoundNotification::class => ['mail'],
            CleanupHasFailedNotification::class => ['mail'],
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ],
        'notifiable' => Notifiable::class,
        'mail' => [
            // Email dev nhận cảnh báo; trống thì gửi về địa chỉ gửi mail của hệ thống.
            'to' => env('DEV_ALERT_EMAIL') ?: env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Page vệ tinh'),
            ],
        ],
        'slack' => ['webhook_url' => '', 'channel' => null, 'username' => null, 'icon' => null],
        'discord' => ['webhook_url' => '', 'username' => '', 'avatar_url' => ''],
        'webhook' => ['url' => ''],
    ],

    'log_channel' => null,

    // Báo lỗi nếu bản sao lưu mới nhất cũ hơn 1 ngày (lịch sao lưu bị dừng mà không ai biết).
    'monitor_backups' => [
        [
            'name' => env('BACKUP_NAME', 'page-ve-tinh'),
            'disks' => ['backup'],
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => 50000,
            ],
        ],
    ],

    // Giữ 30 bản hằng ngày + 12 bản hằng tháng.
    'cleanup' => [
        'strategy' => DefaultStrategy::class,
        'default_strategy' => [
            'keep_all_backups_for_days' => 1,
            'keep_daily_backups_for_days' => 30,
            'keep_weekly_backups_for_weeks' => 0,
            'keep_monthly_backups_for_months' => 12,
            'keep_yearly_backups_for_years' => 0,
            'delete_oldest_backups_when_using_more_megabytes_than' => 50000,
        ],
        'tries' => 2,
        'retry_delay' => 60,
    ],

];
