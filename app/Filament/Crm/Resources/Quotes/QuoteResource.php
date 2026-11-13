<?php

namespace App\Filament\Crm\Resources\Quotes;

use App\Enums\Crm\QuoteStatus;
use App\Filament\Crm\Resources\Quotes\Pages\CreateQuote;
use App\Filament\Crm\Resources\Quotes\Pages\EditQuote;
use App\Filament\Crm\Resources\Quotes\Pages\ListQuotes;
use App\Filament\Crm\Support\CrmFields;
use App\Filament\Crm\Support\LineItems;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Quote;
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

/** Báo giá (cơ hội bán hàng): dòng hàng, VAT, điều kiện, in/lưu PDF; thắng → tạo đơn hàng. */
class QuoteResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = Quote::class;

    protected static ?string $slug = 'bao-gia';

    protected static string|\UnitEnum|null $navigationGroup = 'Bán hàng';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'báo giá';

    protected static ?string $pluralModelLabel = 'Báo giá';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(CrmFields::user())->with(['customer', 'owner', 'site']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Thông tin báo giá')->columns(3)->schema([
                CrmFields::customer()->required()->live()->columnSpan(2),
                Select::make('contact_id')->label('Người nhận')
                    ->options(fn (Get $get): array => Contact::query()->where('customer_id', $get('customer_id'))->pluck('name', 'id')->all()),
                Select::make('status')->label('Trạng thái')->options(QuoteStatus::class)->default(QuoteStatus::Draft)->required()
                    ->disabled()->dehydrated(false)->visibleOn('edit')
                    ->helperText('Đổi bằng các nút Đã gửi / Thắng / Thua ở trên.'),
                CrmFields::owner(),
                Select::make('site_id')->label('Site nguồn')->options(fn () => Site::query()->orderBy('name')->pluck('name', 'id')),
                DatePicker::make('valid_until')->label('Hiệu lực đến')->native(false)->displayFormat('d/m/Y'),
                TextInput::make('vat_rate')->label('Thuế GTGT (%)')->numeric()->minValue(0)->maxValue(10)->default(fn () => CompanyProfile::current()->quote_vat_rate ?? 10)->required(),
                TextInput::make('lost_reason')->label('Lý do thua')->disabled()->visible(fn (?Quote $record): bool => $record?->status === QuoteStatus::Lost),
            ]),
            Section::make('Hàng hoá')->schema([LineItems::repeater()]),
            LineItems::totals(),
            Section::make('Điều kiện')->columns(2)->collapsible()->schema([
                Textarea::make('delivery_terms')->label('Điều kiện giao hàng')->rows(3),
                Textarea::make('payment_terms')->label('Điều kiện thanh toán')->rows(3),
                Textarea::make('notes')->label('Ghi chú')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('Số')->searchable(),
                TextColumn::make('created_at')->label('Ngày')->date('d/m/Y')->sortable(),
                TextColumn::make('customer.name')->label('Khách hàng')->searchable()->wrap(),
                TextColumn::make('total')->label('Tổng tiền')->money('VND', locale: 'vi')->sortable(),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('valid_until')->label('Hiệu lực')->date('d/m/Y')
                    ->color(fn (Quote $record): ?string => $record->isOpen() && $record->valid_until?->isPast() ? 'danger' : null),
                TextColumn::make('site.domain')->label('Site')->placeholder('—')->toggleable(),
                TextColumn::make('owner.name')->label('Phụ trách'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(QuoteStatus::class),
                SelectFilter::make('owner_id')->label('Phụ trách')->options(fn () => User::salesPeople()->pluck('name', 'id')),
                SelectFilter::make('site_id')->label('Site')->options(fn () => Site::query()->orderBy('name')->pluck('domain', 'id')),
                Filter::make('open')->label('Đang mở')->query(fn (Builder $query) => $query->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Negotiating])),
            ])
            ->recordActions([EditAction::make()->label('Mở')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotes::route('/'),
            'create' => CreateQuote::route('/create'),
            'edit' => EditQuote::route('/{record}/edit'),
        ];
    }
}
