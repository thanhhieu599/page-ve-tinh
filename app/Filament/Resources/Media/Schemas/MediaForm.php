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
                FileUpload::make('file')
                    ->label('Ảnh')
                    ->image()
                    ->acceptedFileTypes(MediaLibrary::ACCEPTED_TYPES)
                    ->maxSize(10240)
                    ->storeFiles(false)
                    ->required()
                    ->visibleOn('create')
                    ->helperText('JPEG, PNG hoặc WebP, tối đa 10 MB. Hệ thống tự tạo các bản WebP nhẹ cho điện thoại và máy tính.'),
                self::altInput(),
            ]);
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
