<?php

namespace App\Filament\Pages;

use App\Models\CompanyProfile;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Thông tin D&T dùng chung cho mọi site. Sửa một lần, mọi site (footer, trang giới thiệu,
 * liên hệ, schema Organization) đổi theo. Chỉ Admin.
 *
 * @property-read Schema $form
 */
class CompanyProfileSettings extends Page
{
    protected static ?string $slug = 'thong-tin-cong-ty';

    protected static ?string $title = 'Thông tin công ty D&T';

    protected static ?string $navigationLabel = 'Thông tin công ty';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Hệ thống';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public function mount(): void
    {
        $profile = CompanyProfile::stored();
        $this->form->fill([...$profile->values(), ...$profile->only(CompanyProfile::LIST_FIELDS)]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Pháp lý')->columns(2)->schema([
                TextInput::make('legal_name')->label('Tên pháp lý (đúng giấy phép kinh doanh)')->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('name')->label('Tên ngắn / thương hiệu')->required()->maxLength(100),
                TextInput::make('tax_id')->label('Mã số thuế')->maxLength(20)->regex('/^\d{10}(-\d{3})?$/')
                    ->validationMessages(['regex' => 'Mã số thuế gồm 10 chữ số (hoặc 10 chữ số kèm -XXX cho chi nhánh).']),
            ]),
            Section::make('Liên hệ')->columns(2)->schema([
                TextInput::make('phone')->label('Điện thoại')->maxLength(50)->regex('/^[0-9+().\s-]+$/'),
                TextInput::make('email')->label('Email')->email()->maxLength(255),
                TextInput::make('url')->label('Website chính')->required()->url()->startsWith(['https://'])->maxLength(255),
                TextInput::make('logo_url')->label('Link logo (https, dùng cho Google)')->url()->startsWith(['https://'])->maxLength(255),
            ]),
            Section::make('Địa chỉ')->columns(3)->schema([
                TextInput::make('street_address')->label('Trụ sở: số nhà, đường')->maxLength(255)->columnSpanFull(),
                TextInput::make('locality')->label('Phường/xã, quận/huyện')->maxLength(255),
                TextInput::make('region')->label('Tỉnh/thành phố')->maxLength(255),
                Repeater::make('addresses')->label('Địa chỉ khác (văn phòng giao dịch, kho…)')->columnSpanFull()
                    ->schema([
                        TextInput::make('label')->label('Tên (vd. VPGD, Kho)')->maxLength(50),
                        TextInput::make('address')->label('Địa chỉ')->required()->maxLength(255)->columnSpan(2),
                    ])->columns(3)->defaultItems(0)->maxItems(5)->addActionLabel('Thêm địa chỉ'),
            ]),
            Section::make('Người liên hệ và mạng xã hội (hiện ở footer mọi site)')->columns(2)->schema([
                Repeater::make('contacts')->label('Người liên hệ')->columnSpanFull()
                    ->schema([
                        TextInput::make('name')->label('Tên (vd. Mr Thụy – Kinh doanh)')->maxLength(100),
                        TextInput::make('phone')->label('Điện thoại')->required()->maxLength(50)->regex('/^[0-9+().\s-]+$/'),
                    ])->columns(2)->defaultItems(0)->maxItems(6)->addActionLabel('Thêm người liên hệ'),
                ...array_map(fn (string $key, string $label) => TextInput::make("socials.{$key}")->label($label)
                    ->url()->startsWith(['https://'])->maxLength(255)->placeholder('https://…'),
                    array_keys(CompanyProfile::SOCIALS), CompanyProfile::SOCIALS),
            ]),
            Section::make('Thanh toán và Bộ Công Thương')->columns(2)->schema([
                TagsInput::make('payment_methods')->label('Hình thức thanh toán')->columnSpanFull()
                    ->placeholder('Gõ rồi bấm Enter')->helperText('Trống: dùng danh sách mặc định (chuyển khoản, tiền mặt, công nợ theo hợp đồng).'),
                FileUpload::make('bct_badge')->label('Logo "Đã thông báo Bộ Công Thương"')
                    ->helperText('Chỉ tải lên khi website đã thông báo/đăng ký với Bộ Công Thương. Chưa có thì để trống.')
                    ->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(512)
                    ->disk('public')->directory(CompanyProfile::BADGE_DIRECTORY),
                TextInput::make('bct_url')->label('Link xác minh trên online.gov.vn')->url()
                    ->regex('#^https?://(www\.)?online\.gov\.vn/#')->maxLength(255),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Lưu')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $profile = CompanyProfile::stored();
        $profile->fill($this->form->getState())->save();

        Notification::make()->title('Đã lưu. Mọi site đã dùng thông tin mới.')->success()->send();
    }
}
