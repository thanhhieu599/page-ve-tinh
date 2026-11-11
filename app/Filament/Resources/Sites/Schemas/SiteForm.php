<?php

namespace App\Filament\Resources\Sites\Schemas;

use App\Filament\Support\SiteSettingsSchema;
use App\Models\Site;
use App\Support\Theme;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

/**
 * Cấu hình một site vệ tinh. Công tắc index không nằm ở đây (trang "Mở index (SEO)",
 * có checklist). Thông tin công ty dùng chung, sửa ở "Thông tin công ty".
 */
class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Site')->columns(2)->schema([
                TextInput::make('domain')
                    ->label('Tên miền')
                    ->placeholder('vpptrangthietbi.com.vn')
                    ->helperText('Chỉ tên miền: chữ thường, không https://, không www, không "/".')
                    ->required()
                    ->maxLength(191)
                    ->regex('/^(?!www\.)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/')
                    ->validationMessages(['regex' => 'Nhập đúng dạng tên miền, ví dụ vpptrangthietbi.com.vn (không https://, không www, chữ thường).'])
                    ->unique(ignoreRecord: true)
                    // Tên miền là địa chỉ của site và của trang quản trị site: cố định sau khi tạo.
                    ->disabledOn('edit'),
                Select::make('theme')
                    ->label('Theme')
                    ->options(fn (): array => array_combine(Theme::available(), Theme::available()))
                    ->rules([fn () => Rule::in(Theme::available())])
                    ->helperText('Mỗi theme là một thư mục trong resources/themes.')
                    ->required(),
                Toggle::make('is_active')->label('Đang hoạt động')->default(false)->inline(false)
                    ->helperText('Bật khi đã hoàn thiện và bật hiển thị 3 trang bắt buộc (form báo giá trỏ tới trang chính sách).'),
                Toggle::make('requires_expert_review')->label('Mọi bài cần duyệt chuyên môn (PPE)')->inline(false),
            ]),
            ...SiteSettingsSchema::sections(canEditIntegrations: true),
        ]);
    }
}
