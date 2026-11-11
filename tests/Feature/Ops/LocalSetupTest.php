<?php

namespace Tests\Feature\Ops;

use Tests\TestCase;

/** Cài đặt máy chạy: thông báo lỗi tiếng Việt và lệnh tự kiểm tra môi trường (upload ảnh, PHP extension). */
class LocalSetupTest extends TestCase
{
    public function test_validation_messages_are_in_vietnamese(): void
    {
        app()->setLocale('vi');

        $this->assertSame('Ảnh tải lên không thành công. Xem hướng dẫn: php artisan he-thong:kiem-tra', __('validation.uploaded', ['attribute' => 'Ảnh']));
        $this->assertSame('Vui lòng nhập Email.', __('validation.required', ['attribute' => 'Email']));
        $this->assertSame('Ảnh không được lớn hơn 2048 KB.', __('validation.max.file', ['attribute' => 'Ảnh', 'max' => 2048]));
        $this->assertSame('Ảnh phải là hình ảnh.', __('validation.image', ['attribute' => 'Ảnh']));
    }

    public function test_the_system_check_reports_what_uploads_need(): void
    {
        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('fileinfo')
            ->expectsOutputToContain('Thư mục tạm upload')
            ->expectsOutputToContain('upload_max_filesize')
            ->expectsOutputToContain('storage:link')
            ->assertSuccessful();
    }
}
