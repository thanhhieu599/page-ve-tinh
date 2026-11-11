<?php

namespace App\Filament\Support;

use App\Models\Site;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/**
 * Các nhóm cài đặt của một site, dùng chung cho trang "Cài đặt site" (trong vùng quản trị
 * của từng site) và màn hình "Site vệ tinh" (Admin). Mã đo lường/tích hợp chỉ Admin sửa.
 */
final class SiteSettingsSchema
{
    /** @return list<Section> */
    public static function sections(bool $canEditIntegrations): array
    {
        return [
            Section::make('Nhận diện')->columns(2)->schema([
                TextInput::make('name')->label('Tên site')->required()->maxLength(255),
                ColorPicker::make('settings.primary_color')->label('Màu thương hiệu')->regex(Site::COLOR_PATTERN)
                    ->helperText('Trống: dùng màu đỏ mặc định của theme.'),
                TextInput::make('settings.tagline')->label('Câu giới thiệu trang chủ')->maxLength(200)->columnSpanFull()
                    ->placeholder('Văn phòng phẩm cho nhà máy: báo giá theo số lượng, xuất VAT, giao tận KCN.')
                    ->helperText('Hiện ở trang chủ và làm mô tả trang chủ trên Google.'),
                FileUpload::make('settings.logo')->label('Logo (PNG nền trong, cao tối thiểu 96px)')
                    ->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(1024)
                    ->disk('public')->directory(Site::LOGO_DIRECTORY),
            ]),

            Section::make('Banner trang chủ')
                ->description('Ảnh trượt ngang ở đầu trang chủ. Kích thước khuyên dùng 1200×450 px (JPG/WebP dưới 300 KB). Không có banner thì trang chủ hiện khối chữ giới thiệu.')
                ->schema([
                    Repeater::make('settings.banners')->label('Banner')
                        ->schema([
                            FileUpload::make('image')->label('Ảnh')->required()->image()
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(2048)
                                ->disk('public')->directory(Site::BANNER_DIRECTORY),
                            TextInput::make('alt')->label('Mô tả ảnh (cho người khiếm thị và Google)')->required()->maxLength(150),
                            TextInput::make('url')->label('Link khi bấm (trong site, ví dụ /danh-muc/giay)')->maxLength(255)
                                ->regex('#^/[^/]#')
                                ->validationMessages(['regex' => 'Chỉ link trong site, bắt đầu bằng "/" (ví dụ /danh-muc/giay).']),
                        ])
                        ->columns(3)->maxItems(Site::MAX_BANNERS)->reorderable()->collapsible()->defaultItems(0)
                        ->addActionLabel('Thêm banner'),
                ]),

            Section::make('Đo lường và tích hợp')
                ->description($canEditIntegrations ? null : 'Chỉ Admin sửa được các mã này.')
                ->columns(2)->schema([
                    TextInput::make('settings.ga4_id')->label('GA4 Measurement ID')->placeholder('G-XXXXXXXXXX')
                        ->regex(Site::GA4_PATTERN)->disabled(! $canEditIntegrations),
                    TextInput::make('settings.gsc_verification')->label('Mã xác minh Search Console')
                        ->helperText('Phần content của thẻ meta google-site-verification.')
                        ->regex(Site::GSC_TOKEN_PATTERN)->disabled(! $canEditIntegrations),
                    TextInput::make('settings.gsc_property')->label('Property Search Console (kéo số liệu)')
                        ->placeholder('sc-domain:vpptrangthietbi.com.vn')
                        ->regex(Site::GSC_PROPERTY_PATTERN)->disabled(! $canEditIntegrations),
                    TextInput::make('settings.mautic_campaign_id')->label('Mautic campaign ID')->integer()->minValue(1)
                        ->disabled(! $canEditIntegrations),
                    TextInput::make('settings.industry_tag')->label('Mã ngành (tag Mautic nganh-…)')->placeholder('van-phong-pham')
                        ->regex(Site::INDUSTRY_TAG_PATTERN)->disabled(! $canEditIntegrations),
                ]),
        ];
    }

    /**
     * Gộp cài đặt mới vào cài đặt đang lưu: ô bị khoá (không gửi lên) giữ nguyên giá trị cũ.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeSettings(Site $site, array $data): array
    {
        if (array_key_exists('settings', $data)) {
            $data['settings'] = array_merge($site->settings ?? [], (array) $data['settings']);
        }

        return $data;
    }
}
