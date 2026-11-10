<?php

namespace App\Filament\Resources\Sites\Schemas;

use App\Support\Theme;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
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
                    ->unique(ignoreRecord: true),
                TextInput::make('name')->label('Tên site')->required()->maxLength(255),
                Select::make('theme')
                    ->label('Theme')
                    ->options(fn (): array => array_combine(Theme::available(), Theme::available()))
                    ->rules([fn () => Rule::in(Theme::available())])
                    ->helperText('Mỗi theme là một thư mục trong resources/themes.')
                    ->required(),
                Toggle::make('is_active')->label('Đang hoạt động')->default(true)->inline(false),
                Toggle::make('requires_expert_review')->label('Mọi bài cần duyệt chuyên môn (PPE)')->inline(false),
            ]),
            Section::make('Giao diện')->columns(2)->schema([
                FileUpload::make('settings.logo')
                    ->label('Logo')
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(1024)
                    ->disk('public')
                    ->directory('site-logos'),
                ColorPicker::make('settings.primary_color')
                    ->label('Màu thương hiệu')
                    ->regex('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'),
            ]),
            Section::make('Đo lường và Mautic')->columns(2)->schema([
                TextInput::make('settings.ga4_id')->label('GA4 Measurement ID')->placeholder('G-XXXXXXXXXX')
                    ->regex('/^G-[A-Z0-9]{4,20}$/'),
                TextInput::make('settings.gsc_verification')->label('Mã xác minh Search Console')
                    ->helperText('Phần content của thẻ meta google-site-verification.')
                    ->regex('/^[A-Za-z0-9_-]{10,100}$/'),
                TextInput::make('settings.mautic_campaign_id')->label('Mautic campaign ID')->integer()->minValue(1),
                TextInput::make('settings.industry_tag')->label('Mã ngành (tag Mautic nganh-…)')->placeholder('van-phong-pham')
                    ->regex('/^[a-z0-9-]{1,50}$/'),
            ]),
        ]);
    }
}
