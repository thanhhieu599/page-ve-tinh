<?php

namespace App\Filament\Pages;

use App\Models\CompanyProfile;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
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
        $this->form->fill(CompanyProfile::stored()->values());
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
                TextInput::make('street_address')->label('Số nhà, đường')->maxLength(255)->columnSpanFull(),
                TextInput::make('locality')->label('Phường/xã, quận/huyện')->maxLength(255),
                TextInput::make('region')->label('Tỉnh/thành phố')->maxLength(255),
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
