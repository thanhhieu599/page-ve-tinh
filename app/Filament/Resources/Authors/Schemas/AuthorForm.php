<?php

namespace App\Filament\Resources\Authors\Schemas;

use App\Models\Author;
use App\Support\MediaLibrary;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuthorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Hồ sơ tác giả')
                    ->description('Hiện ở trang /tac-gia/… trên mọi site. Ghi rõ kinh nghiệm thật, chứng chỉ (ISO 45001, an toàn lao động…) để tăng độ tin cậy với Google và khách hàng.')
                    ->schema([
                        TextInput::make('name')->label('Họ tên')->required()->maxLength(255),
                        TextInput::make('slug')->label('Slug')->prefix(Author::PATH_PREFIX)->maxLength(255)
                            ->helperText('Để trống sẽ tự sinh từ họ tên.')
                            ->rules(['nullable', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                            ->unique(ignoreRecord: true),
                        TextInput::make('job_title')->label('Chức danh')->maxLength(255),
                        Textarea::make('bio')->label('Tiểu sử')->rows(4),
                        Textarea::make('expertise')->label('Chuyên môn')->rows(2),
                        FileUpload::make('avatar')->label('Ảnh đại diện')->image()
                            ->acceptedFileTypes(MediaLibrary::ACCEPTED_TYPES)->disk('public')->directory('authors'),
                        TextInput::make('linkedin_url')->label('LinkedIn')->url()->maxLength(255)
                            ->rules(['nullable', 'starts_with:https://']),
                    ]),
            ]);
    }
}
