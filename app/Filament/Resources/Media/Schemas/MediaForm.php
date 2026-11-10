<?php

namespace App\Filament\Resources\Media\Schemas;

use App\Support\MediaLibrary;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                self::uploadInput('file')
                    ->label('Ảnh')
                    ->required()
                    ->visibleOn('create'),
                self::altInput(),
            ]);
    }

    /** Ô tải ảnh lên thư viện: chỉ JPEG/PNG/WebP, giữ file tạm để MediaLibrary xử lý. */
    public static function uploadInput(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->acceptedFileTypes(MediaLibrary::ACCEPTED_TYPES)
            ->maxSize(MediaLibrary::MAX_KB)
            ->storeFiles(false)
            ->helperText('JPEG, PNG hoặc WebP, tối đa 10 MB. Hệ thống tự tạo các bản WebP nhẹ cho điện thoại và máy tính.');
    }

    public static function altInput(): TextInput
    {
        return TextInput::make('alt')
            ->label('Mô tả ảnh (alt)')
            ->required()
            ->maxLength(255)
            ->helperText('Mô tả đúng nội dung ảnh, ví dụ "Găng tay chống cắt cấp độ 5 theo EN 388". Bắt buộc: giúp Google hiểu ảnh và người khiếm thị đọc được.');
    }
}
