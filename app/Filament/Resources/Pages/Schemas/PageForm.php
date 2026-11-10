<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PageForm
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
            Textarea::make('meta_description')->label('Meta description')->helperText('Nên 120–160 ký tự.')->maxLength(300)->rows(2),
            Toggle::make('is_published')->label('Hiển thị trên site')
                ->helperText('Trang chính sách quyền riêng tư cần pháp chế duyệt trước khi bật.'),
        ]);
    }
}
