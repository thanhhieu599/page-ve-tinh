<?php

namespace App\Filament\Resources\RequiredPages\Schemas;

use App\Enums\PageKind;
use App\Models\RequiredPage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RequiredPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('title')->label('Tiêu đề')->required()->maxLength(255),
            RichEditor::make('content')
                ->label('Nội dung')
                ->helperText('Thông tin công ty (tên pháp lý, MST, địa chỉ, liên hệ) tự hiện bên dưới trang giới thiệu và liên hệ, không cần gõ lại. Thay mọi đoạn [CẦN BỔ SUNG]/[CẦN XÁC NHẬN] trước khi bật hiển thị.')
                ->required()
                ->fileAttachments(false)
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'link'],
                    ['h2', 'h3'],
                    ['bulletList', 'orderedList', 'blockquote'],
                    ['undo', 'redo'],
                ]),
            Section::make('Khối trang giới thiệu')
                ->description('Hiện thành các phần trình bày riêng: ảnh đầu trang, số liệu, cột mốc, giá trị, hình ảnh, khách hàng. Bỏ trống phần nào thì phần đó không hiện.')
                ->visible(fn (?RequiredPage $record): bool => $record?->kind === PageKind::About)
                ->schema([
                    FileUpload::make('extras.hero_image')->label('Ảnh đầu trang (kho, văn phòng, đội ngũ – ngang, 1200×800)')
                        ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(2048)
                        ->disk('public')->directory(RequiredPage::IMAGE_DIRECTORY),
                    Repeater::make('extras.stats')->label('Số liệu nổi bật')->schema([
                        TextInput::make('value')->label('Con số (vd. 10+)')->required()->maxLength(20),
                        TextInput::make('label')->label('Ý nghĩa (vd. năm kinh nghiệm)')->required()->maxLength(60),
                    ])->columns(2)->maxItems(4)->defaultItems(0)->addActionLabel('Thêm số liệu'),
                    Repeater::make('extras.milestones')->label('Cột mốc phát triển')->schema([
                        TextInput::make('year')->label('Năm')->required()->maxLength(10),
                        TextInput::make('title')->label('Sự kiện')->required()->maxLength(120),
                        Textarea::make('description')->label('Mô tả ngắn')->rows(2)->maxLength(300)->columnSpanFull(),
                    ])->columns(2)->maxItems(15)->defaultItems(0)->reorderable()->collapsible()->addActionLabel('Thêm cột mốc'),
                    Repeater::make('extras.values')->label('Giá trị cốt lõi / cam kết')->schema([
                        TextInput::make('title')->label('Tiêu đề')->required()->maxLength(60),
                        Textarea::make('description')->label('Mô tả')->rows(2)->maxLength(250),
                    ])->columns(2)->maxItems(6)->defaultItems(0)->addActionLabel('Thêm giá trị'),
                    FileUpload::make('extras.gallery')->label('Hình ảnh hoạt động (tối đa 8)')->multiple()->reorderable()->maxFiles(8)
                        ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(2048)
                        ->disk('public')->directory(RequiredPage::IMAGE_DIRECTORY),
                    TagsInput::make('extras.clients')->label('Khách hàng tiêu biểu (gõ tên rồi Enter)')
                        ->helperText('Chỉ ghi khách hàng đã đồng ý cho nêu tên.'),
                ]),
            Textarea::make('meta_description')->label('Meta description')->helperText('Nên 120–160 ký tự.')->maxLength(300)->rows(2),
            Toggle::make('is_published')->label('Hiển thị trên site')
                ->helperText('Trang chính sách quyền riêng tư cần pháp chế duyệt trước khi bật.'),
        ]);
    }
}
