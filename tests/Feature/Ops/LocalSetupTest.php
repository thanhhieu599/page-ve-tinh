<?php

namespace Tests\Feature\Ops;

use App\Http\Middleware\RecordUploadFailure;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Cài đặt máy chạy: thông báo lỗi tiếng Việt và lệnh tự kiểm tra môi trường (upload ảnh, PHP extension). */
class LocalSetupTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(storage_path(RecordUploadFailure::FILE));

        parent::tearDown();
    }

    public function test_validation_messages_are_in_vietnamese(): void
    {
        app()->setLocale('vi');

        $this->assertSame('Ảnh tải lên không thành công. Xem hướng dẫn: php artisan he-thong:kiem-tra', __('validation.uploaded', ['attribute' => 'Ảnh']));
        $this->assertSame('Vui lòng nhập Email.', __('validation.required', ['attribute' => 'Email']));
        $this->assertSame('Ảnh không được lớn hơn 2048 KB.', __('validation.max.file', ['attribute' => 'Ảnh', 'max' => 2048]));
        $this->assertSame('Ảnh phải là hình ảnh.', __('validation.image', ['attribute' => 'Ảnh']));
    }

    public function test_a_failed_admin_upload_is_recorded_and_shown_by_the_system_check(): void
    {
        // Link tải lên hết hạn/sai chữ ký: Livewire trả 401, giao diện chỉ báo "không thành công".
        $this->post(route('livewire.upload-file'), ['files' => [UploadedFile::fake()->image('banner.jpg')]])->assertStatus(401);

        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('Lần tải lên lỗi gần nhất')
            ->expectsOutputToContain('Tệp banner.jpg:')
            ->expectsOutputToContain('Gợi ý: link tải lên hết hạn hoặc sai chữ ký')
            ->assertSuccessful();
    }

    public function test_the_system_check_tries_a_real_temporary_upload(): void
    {
        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('Thử lưu ảnh tạm như khi tải lên')
            ->assertSuccessful();
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
