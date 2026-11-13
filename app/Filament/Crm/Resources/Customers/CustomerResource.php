<?php

namespace App\Filament\Crm\Resources\Customers;

use App\Enums\Crm\CustomerStatus;
use App\Enums\Crm\CustomerType;
use App\Filament\Crm\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Crm\Resources\Customers\Pages\EditCustomer;
use App\Filament\Crm\Resources\Customers\Pages\ListCustomers;
use App\Filament\Crm\Support\CrmFields;
use App\Models\Customer;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/** Khách hàng doanh nghiệp dùng chung mọi site: thông tin công ty, người liên hệ, lịch sử giao dịch. */
class CustomerResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = Customer::class;

    protected static ?string $slug = 'khach-hang';

    protected static string|\UnitEnum|null $navigationGroup = 'Bán hàng';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'khách hàng';

    protected static ?string $pluralModelLabel = 'Khách hàng';

    protected static ?string $recordTitleAttribute = 'name';

    /** Ngành hàng gợi ý (khách mục tiêu của D&T). */
    public const INDUSTRIES = ['Điện tử', 'Cơ khí', 'Thực phẩm', 'Thép / luyện kim', 'Dầu khí', 'Kính', 'Dệt may', 'Xây dựng', 'Hoá chất', 'Logistics', 'Văn phòng / dịch vụ', 'Khác'];

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(CrmFields::user())->with(['owner', 'firstSite']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Doanh nghiệp')->columns(3)->schema([
                TextInput::make('name')->label('Tên công ty')->required()->maxLength(255)->columnSpan(2),
                TextInput::make('tax_code')->label('Mã số thuế')->maxLength(20)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule),
                Select::make('type')->label('Loại')->options(CustomerType::class),
                Select::make('industry')->label('Ngành')->options(array_combine(self::INDUSTRIES, self::INDUSTRIES))->searchable(),
                TextInput::make('size')->label('Quy mô (số lao động)')->maxLength(50),
                TextInput::make('address')->label('Địa chỉ')->maxLength(255)->columnSpan(2),
                TextInput::make('industrial_zone')->label('Khu công nghiệp')->maxLength(255),
                TextInput::make('province')->label('Tỉnh / thành')->maxLength(100),
                TextInput::make('website')->label('Website')->url()->maxLength(255),
                TextInput::make('email_domain')->label('Tên miền email công ty')->placeholder('samsung.com')
                    ->helperText('Lead mới có email @tên-miền này tự gắn vào khách hàng.')->maxLength(255),
            ]),
            Section::make('Quản lý')->columns(3)->schema([
                Select::make('status')->label('Trạng thái')->options(CustomerStatus::class)->default(CustomerStatus::Prospect)->required(),
                CrmFields::owner(),
                Select::make('first_site_id')->label('Site đầu tiên')->options(fn () => Site::query()->orderBy('name')->pluck('name', 'id')),
                TextInput::make('reorder_cycle_days')->label('Chu kỳ tái đặt hàng (ngày)')->integer()->minValue(7)->maxValue(365)
                    ->helperText('VD: găng tay 30 ngày. Để trống dùng mặc định trong Cài đặt.'),
                DatePicker::make('next_reorder_at')->label('Ngày nhắc tái đặt hàng')->native(false)->displayFormat('d/m/Y'),
                Textarea::make('notes')->label('Ghi chú (tiêu chuẩn PPE yêu cầu, quy trình duyệt NCC, eAuction…)')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Công ty')->searchable(['name', 'tax_code', 'email_domain'])->wrap()
                    ->description(fn (Customer $record): ?string => trim(implode(' · ', array_filter([$record->industrial_zone, $record->province])))),
                TextColumn::make('type')->label('Loại')->badge(),
                TextColumn::make('industry')->label('Ngành')->toggleable(),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('owner.name')->label('Phụ trách')->placeholder('—'),
                TextColumn::make('revenue')->label('Doanh số')
                    ->state(fn (Customer $record): string => Order::money((int) $record->orders()->where('status', '!=', 'cancelled')->sum('total'))),
                TextColumn::make('debt')->label('Công nợ')
                    ->state(fn (Customer $record): string => Order::money($record->outstanding())),
                TextColumn::make('next_reorder_at')->label('Nhắc tái đặt')->date('d/m/Y')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(CustomerStatus::class),
                SelectFilter::make('type')->label('Loại')->options(CustomerType::class),
                SelectFilter::make('industry')->label('Ngành')->options(array_combine(self::INDUSTRIES, self::INDUSTRIES)),
                SelectFilter::make('owner_id')->label('Phụ trách')->options(fn () => User::salesPeople()->pluck('name', 'id')),
                SelectFilter::make('first_site_id')->label('Site đầu tiên')->options(fn () => Site::query()->orderBy('name')->pluck('domain', 'id')),
            ])
            ->recordActions([EditAction::make()->label('Xem')]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ContactsRelationManager::class,
            RelationManagers\QuotesRelationManager::class,
            RelationManagers\OrdersRelationManager::class,
            RelationManagers\ActivitiesRelationManager::class,
            RelationManagers\TasksRelationManager::class,
            RelationManagers\LeadsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
