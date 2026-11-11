<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Ghi lại lý do khi tải tệp lên trang quản trị thất bại (Livewire chỉ báo chung "không thành công").
 * `php artisan he-thong:kiem-tra` đọc file này để chỉ đúng nguyên nhân trên máy đang chạy.
 */
class RecordUploadFailure
{
    public const FILE = 'logs/last-upload-failure.json';

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $e) {
            self::record($request, 500, $e::class.': '.$e->getMessage());

            throw $e;
        }

        if ($response->getStatusCode() >= 400) {
            $json = json_decode((string) $response->getContent(), true);
            $detail = ($response->exception ?? null)?->getMessage()
                ?: (is_array($json) && is_string($json['message'] ?? null) ? $json['message'] : '')
                ?: (Response::$statusTexts[$response->getStatusCode()] ?? '');
            self::record($request, $response->getStatusCode(), (string) $detail);
        }

        return $response;
    }

    private static function record(Request $request, int $status, string $detail): void
    {
        // Mã lỗi PHP của từng tệp (1 = vượt upload_max_filesize, 6 = thiếu thư mục tạm, 7 = không ghi được…).
        $phpErrors = [];
        array_walk_recursive($_FILES, function ($value, $key) use (&$phpErrors): void {
            if ($key === 'error' && (int) $value !== UPLOAD_ERR_OK) {
                $phpErrors[] = (int) $value;
            }
        });

        // Từng tệp PHP nhận được: cỡ, mã lỗi, PHP có coi là tệp tải lên hợp lệ không (is_uploaded_file).
        $files = [];
        foreach (Arr::flatten($request->allFiles()) as $file) {
            if ($file instanceof UploadedFile) {
                $files[] = [
                    'name' => $file->getClientOriginalName(),
                    'size' => (int) @filesize($file->getPathname()),
                    'error' => $file->getError(),
                    'is_uploaded_file' => is_uploaded_file($file->getPathname()),
                    'tmp' => $file->getPathname(),
                ];
            }
        }

        $entry = [
            'at' => now()->toIso8601String(),
            'status' => $status,
            'detail' => trim($detail),
            'php_upload_errors' => $phpErrors,
            'files' => $files,
            'server' => PHP_SAPI.' '.PHP_VERSION,
            'content_length' => (int) $request->header('Content-Length'),
        ];

        Log::warning('Tải tệp lên thất bại', $entry);
        @file_put_contents(storage_path(self::FILE), json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
