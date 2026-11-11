<?php

namespace App\Filament\Resources\RequiredPages\Schemas;

use App\Enums\PageKind;
use App\Models\Category;
use App\Models\RequiredPage;
use App\Support\AboutIcons;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RequiredPageForm
{
    /**
     * Trang giới thiệu kiểu web thương mại: mỗi khối một phần nhập riêng, cùng bố cục cho mọi site.
     * Thông tin công ty (khối cuối) lấy từ "Thông tin công ty" chung.
     *
     * @return list<Section>
     */
    private static function aboutSections(): array
    {
        $isAbout = fn (?RequiredPage $record): bool => $record?->kind === PageKind::About;
        $image = fn (string $name, string $label) => FileUpload::make($name)->label($label)
            ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(2048)
            ->disk('public')->directory(RequiredPage::IMAGE_DIRECTORY);

        return [
            Section::make('1. Banner đầu trang')
                ->description('Ảnh tràn ngang, câu định vị và 2 nút "Yêu cầu báo giá", "Xem sản phẩm". Chưa có ảnh thì nền đen chữ trắng.')
                ->visible($isAbout)
                ->schema([
                    TextInput::make('extras.headline')->label('Câu định vị (tiêu đề lớn)')
                        ->placeholder('Đối tác cung ứng văn phòng phẩm cho nhà máy FDI')->maxLength(120),
                    TextInput::make('extras.summary')->label('Mô tả ngắn dưới tiêu đề')->maxLength(200),
                    $image('extras.hero_image', 'Ảnh banner (kho, văn phòng, đội ngũ – ngang, 1600×600)'),
                ]),
            Section::make('2. Số liệu nổi bật')
                ->visible($isAbout)
                ->schema([
                    Repeater::make('extras.stats')->hiddenLabel()->schema([
                        TextInput::make('value')->label('Con số (vd. 10+)')->required()->maxLength(20),
                        TextInput::make('label')->label('Ý nghĩa (vd. năm kinh nghiệm)')->required()->maxLength(60),
                    ])->columns(2)->maxItems(4)->defaultItems(0)->addActionLabel('Thêm số liệu'),
                ]),
            Section::make('3. Về chúng tôi')
                ->visible($isAbout)
                ->schema([
                    Textarea::make('extras.intro')->label('Đoạn giới thiệu')
                        ->helperText('Ít nhất '.RequiredPage::ABOUT_MIN_INTRO_WORDS.' chữ: công ty làm gì, cho ai, mạnh ở đâu (Google và AI đọc đoạn này).')
                        ->rows(6)->maxLength(2000),
                    $image('extras.intro_image', 'Ảnh bên cạnh (đội ngũ, kho – 800×600)'),
                ]),
            Section::make('4. Chúng tôi cung cấp gì')
                ->description('Chọn tối đa '.RequiredPage::ABOUT_MAX_CATEGORIES.' danh mục; ảnh, tên và link tự lấy theo danh mục. Không chọn thì khối này ẩn.')
                ->visible($isAbout)
                ->schema([
                    Select::make('extras.category_ids')->hiddenLabel()->multiple()
                        ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->maxItems(RequiredPage::ABOUT_MAX_CATEGORIES)
                        ->rules(['array', 'max:'.RequiredPage::ABOUT_MAX_CATEGORIES]),
                ]),
            Section::make('5. Vì sao chọn chúng tôi')
                ->description('Từ '.RequiredPage::ABOUT_MIN_VALUES.' đến '.RequiredPage::ABOUT_MAX_VALUES.' cam kết.')
                ->visible($isAbout)
                ->schema([
                    Repeater::make('extras.values')->hiddenLabel()->schema([
                        Select::make('icon')->label('Biểu tượng')->options(AboutIcons::options())->default('shield')->required(),
                        TextInput::make('title')->label('Tiêu đề')->required()->maxLength(60),
                        Textarea::make('description')->label('Mô tả (1 câu)')->rows(2)->maxLength(200)->columnSpanFull(),
                    ])->columns(2)->maxItems(RequiredPage::ABOUT_MAX_VALUES)->defaultItems(0)->addActionLabel('Thêm cam kết'),
                ]),
            Section::make('6. Khách hàng tiêu biểu')
                ->description('Chỉ ghi khách hàng đã đồng ý cho nêu tên. Chưa có logo thì hiện tên.')
                ->visible($isAbout)
                ->schema([
                    Repeater::make('extras.clients')->hiddenLabel()->schema([
                        TextInput::make('name')->label('Tên khách hàng')->required()->maxLength(80),
                        $image('logo', 'Logo (PNG nền trong)'),
                    ])->columns(2)->maxItems(12)->defaultItems(0)->reorderable()->addActionLabel('Thêm khách hàng'),
                ]),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('title')->label('Tiêu đề')->required()->maxLength(255),
            RichEditor::make('content')
                ->label('Nội dung')
                ->helperText('Thông tin công ty (tên pháp lý, MST, địa chỉ, liên hệ) tự hiện bên dưới trang, không cần gõ lại. Thay mọi đoạn [CẦN BỔ SUNG]/[CẦN XÁC NHẬN] trước khi bật hiển thị.')
                // Trang giới thiệu dùng các ô riêng bên dưới thay cho ô soạn thảo.
                ->hidden(fn (?RequiredPage $record): bool => $record?->kind === PageKind::About)
                ->required(fn (?RequiredPage $record): bool => $record?->kind !== PageKind::About)
                ->fileAttachments(false)
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'link'],
                    ['h2', 'h3'],
                    ['bulletList', 'orderedList', 'blockquote'],
                    ['undo', 'redo'],
                ]),
            ...self::aboutSections(),
            Textarea::make('meta_description')->label('Meta description')->helperText('Nên 120–160 ký tự.')->maxLength(300)->rows(2),
            Toggle::make('is_published')->label('Hiển thị trên site')
                ->helperText('Trang chính sách quyền riêng tư cần pháp chế duyệt trước khi bật.'),
        ]);
    }
}
