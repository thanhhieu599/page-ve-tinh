<?php

namespace App\Filament\Crm\Pages;

use App\Filament\Crm\Support\CrmFields;
use App\Models\CompanyProfile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
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
 * Cài đặt CRM: mặc định cho báo giá/đơn hàng (VAT, hiệu lực, điều kiện, tài khoản, hạn thanh toán,
 * chu kỳ tái đặt hàng). Tên, MST, địa chỉ D&T lấy từ "Thông tin công ty" (trang quản trị nội dung).
 *
 * @property-read Schema $form
 */
class CrmSettings extends Page
{
    protected static ?string $slug = 'cai-dat';

    protected static ?string $title = 'Cài đặt CRM';

    protected static string|\UnitEnum|null $navigationGroup = 'Cấu hình';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check() && CrmFields::user()->isSalesManager();
    }

    public function mount(): void
    {
        $this->form->fill(CompanyProfile::stored()->only(CompanyProfile::CRM_FIELDS));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Báo giá và đơn hàng')->columns(3)->schema([
                TextInput::make('quote_vat_rate')->label('Thuế GTGT mặc định (%)')->numeric()->minValue(0)->maxValue(10)->placeholder('10'),
                TextInput::make('quote_valid_days')->label('Hiệu lực báo giá (ngày)')->integer()->minValue(1)->maxValue(180)->placeholder('15'),
                TextInput::make('payment_due_days')->label('Hạn thanh toán sau ngày đặt (ngày)')->integer()->minValue(0)->maxValue(180)->placeholder('30'),
                Textarea::make('quote_delivery_terms')->label('Điều kiện giao hàng mặc định')->rows(3)->columnSpan(2)
                    ->placeholder('Giao tận kho nhà máy trong 3–5 ngày làm việc kể từ khi xác nhận đơn.'),
                TextInput::make('bank_account')->label('Tài khoản ngân hàng (in trên báo giá)')->maxLength(255),
                Textarea::make('quote_payment_terms')->label('Điều kiện thanh toán mặc định')->rows(3)->columnSpanFull()
                    ->placeholder('Chuyển khoản trong 30 ngày kể từ ngày xuất hoá đơn VAT.'),
            ]),
            Section::make('Chăm sóc')->schema([
                TextInput::make('default_reorder_cycle_days')->label('Chu kỳ tái đặt hàng mặc định (ngày)')->integer()->minValue(7)->maxValue(365)
                    ->helperText('Giao xong đơn → ngày nhắc đặt lại = ngày giao + chu kỳ (khách có chu kỳ riêng thì dùng chu kỳ riêng). Để trống: không tự nhắc.'),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Lưu')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        CompanyProfile::stored()->fill($this->form->getState())->save();
        Notification::make()->title('Đã lưu cài đặt CRM')->success()->send();
    }
}
