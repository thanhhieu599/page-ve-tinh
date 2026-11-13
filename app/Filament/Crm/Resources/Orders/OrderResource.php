<?php

namespace App\Filament\Crm\Resources\Orders;

use App\Enums\Crm\OrderStatus;
use App\Enums\Crm\PaymentStatus;
use App\Filament\Crm\Resources\Orders\Pages\CreateOrder;
use App\Filament\Crm\Resources\Orders\Pages\EditOrder;
use App\Filament\Crm\Resources\Orders\Pages\ListOrders;
use App\Filament\Crm\Support\CrmFields;
use App\Filament\Crm\Support\LineItems;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Đơn hàng: giao hàng, hoá đơn VAT, thanh toán và công nợ. Hoàn tất → tính ngày nhắc tái đặt hàng. */
class OrderResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = Order::class;

    protected static ?string $slug = 'don-hang';

    protected static string|\UnitEnum|null $navigationGroup = 'Bán hàng';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $modelLabel = 'đơn hàng';

    protected static ?string $pluralModelLabel = 'Đơn hàng';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(CrmFields::user())->with(['customer', 'owner', 'site']);
    }

    /** Huy hiệu: đơn mới chưa xử lý. */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $count = $user instanceof User ? Order::query()->visibleTo($user)->where('status', OrderStatus::New)->count() : 0;

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Đơn hàng')->columns(3)->schema([
                CrmFields::customer()->required()->live()->columnSpan(2),
                Select::make('contact_id')->label('Người nhận hàng')
                    ->options(fn (Get $get): array => Contact::query()->where('customer_id', $get('customer_id'))->pluck('name', 'id')->all()),
                Select::make('status')->label('Trạng thái')->options(OrderStatus::class)->default(OrderStatus::New)->required(),
                CrmFields::owner(),
                Select::make('site_id')->label('Site nguồn')->options(fn () => Site::query()->orderBy('name')->pluck('name', 'id')),
                DatePicker::make('ordered_at')->label('Ngày đặt')->default(now())->required()->native(false)->displayFormat('d/m/Y'),
                DatePicker::make('delivered_at')->label('Ngày giao xong')->native(false)->displayFormat('d/m/Y'),
                TextInput::make('vat_rate')->label('Thuế GTGT (%)')->numeric()->minValue(0)->maxValue(10)->default(fn () => CompanyProfile::current()->quote_vat_rate ?? 10)->required(),
            ]),
            Section::make('Hàng hoá')->schema([LineItems::repeater()]),
            LineItems::totals(),
            Section::make('Hoá đơn và công nợ')->columns(3)->schema([
                TextInput::make('invoice_number')->label('Số hoá đơn VAT')->maxLength(50),
                DatePicker::make('due_date')->label('Hạn thanh toán')->native(false)->displayFormat('d/m/Y'),
                TextInput::make('paid_amount')->label('Đã thu (đ)')->integer()->minValue(0)->default(0),
                Textarea::make('notes')->label('Ghi chú')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('Số')->searchable(['number', 'invoice_number']),
                TextColumn::make('ordered_at')->label('Ngày đặt')->date('d/m/Y')->sortable(),
                TextColumn::make('customer.name')->label('Khách hàng')->searchable()->wrap(),
                TextColumn::make('total')->label('Tổng tiền')->money('VND', locale: 'vi')->sortable(),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('payment_status')->label('Thanh toán')->badge(),
                TextColumn::make('debt')->label('Còn nợ')->state(fn (Order $record): string => Order::money($record->outstanding())),
                TextColumn::make('due_date')->label('Hạn TT')->date('d/m/Y')->sortable()
                    ->color(fn (Order $record): ?string => $record->outstanding() > 0 && $record->due_date?->isPast() ? 'danger' : null),
                TextColumn::make('owner.name')->label('Phụ trách')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(OrderStatus::class),
                SelectFilter::make('payment_status')->label('Thanh toán')->options(PaymentStatus::class),
                Filter::make('overdue')->label('Công nợ quá hạn')->query(fn (Builder $query) => $query->overdue()),
                SelectFilter::make('owner_id')->label('Phụ trách')->options(fn () => User::salesPeople()->pluck('name', 'id')),
                SelectFilter::make('site_id')->label('Site')->options(fn () => Site::query()->orderBy('name')->pluck('domain', 'id')),
            ])
            ->recordActions([EditAction::make()->label('Mở')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
