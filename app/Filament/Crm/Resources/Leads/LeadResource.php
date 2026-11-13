<?php

namespace App\Filament\Crm\Resources\Leads;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadChannel;
use App\Enums\LeadStatus;
use App\Filament\Crm\Resources\Customers\CustomerResource;
use App\Filament\Crm\Resources\Leads\Pages\CreateLead;
use App\Filament\Crm\Resources\Leads\Pages\EditLead;
use App\Filament\Crm\Resources\Leads\Pages\ListLeads;
use App\Filament\Crm\Resources\Quotes\QuoteResource;
use App\Filament\Crm\Support\CrmFields;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\Site;
use App\Models\User;
use App\Support\Crm\CrmNotifier;
use App\Support\Crm\CustomerMatcher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Khách hàng tiềm năng từ MỌI site vệ tinh (form báo giá) và kênh nhập tay (điện thoại, Zalo, hội chợ…).
 * Trưởng phòng KD giao lead; nhân viên chỉ thấy lead của mình.
 */
class LeadResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = Lead::class;

    protected static ?string $slug = 'khach-hang-tiem-nang';

    protected static string|\UnitEnum|null $navigationGroup = 'Bán hàng';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleOvalLeftEllipsis;

    protected static ?string $modelLabel = 'khách hàng tiềm năng';

    protected static ?string $pluralModelLabel = 'Khách hàng tiềm năng';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes()->visibleTo(CrmFields::user())->with(['site', 'customer', 'assignee']);
    }

    /** Huy hiệu: lead mới chưa giao (trưởng phòng) / lead mới của tôi (nhân viên). */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }
        $query = Lead::withoutGlobalScopes()->where('status', LeadStatus::New);
        $count = $user->isSalesManager() ? $query->whereNull('assigned_to')->count() : $query->where('assigned_to', $user->id)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Khách hàng')->columns(2)->schema([
                Select::make('channel')->label('Kênh')->options(LeadChannel::class)->default(LeadChannel::Phone)->required(),
                Select::make('site_id')->label('Site nguồn')->options(fn () => Site::query()->orderBy('name')->pluck('name', 'id'))->placeholder('Không qua website'),
                TextInput::make('name')->label('Họ tên')->required()->maxLength(255),
                TextInput::make('company')->label('Công ty')->required()->maxLength(255),
                TextInput::make('email')->label('Email')->email()->maxLength(255),
                TextInput::make('phone')->label('Điện thoại')->tel()->maxLength(50),
                Textarea::make('need')->label('Nhu cầu (sản phẩm, số lượng, thời gian, địa điểm giao)')->required()->rows(4)->columnSpanFull(),
            ]),
            Section::make('Xử lý')->columns(3)->schema([
                Select::make('status')->label('Trạng thái')->options(LeadStatus::class)->default(LeadStatus::New)->required(),
                CrmFields::owner('assigned_to', 'Giao cho'),
                CrmFields::customer()->helperText('Tự gắn nếu trùng email/SĐT/tên miền công ty.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Nhận lúc')->dateTime('d/m/Y H:i', config('app.display_timezone'))->sortable(),
                TextColumn::make('site.domain')->label('Nguồn')->badge()->color('gray')
                    ->state(fn (Lead $record): string => $record->site?->domain ?? $record->channel?->getLabel() ?? '—'),
                TextColumn::make('company')->label('Công ty')->searchable()->wrap()
                    ->description(fn (Lead $record): string => $record->name),
                TextColumn::make('phone')->label('Liên hệ')->searchable(['phone', 'email', 'name'])
                    ->description(fn (Lead $record): ?string => $record->email),
                TextColumn::make('need')->label('Nhu cầu')->limit(60)->wrap()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('customer.name')->label('Khách hàng')->placeholder('Chưa gắn')
                    ->url(fn (Lead $record): ?string => $record->customer_id ? CustomerResource::getUrl('edit', ['record' => $record->customer_id]) : null),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('assignee.name')->label('Phụ trách')->placeholder('Chưa giao'),
            ])
            ->filters([
                SelectFilter::make('site_id')->label('Site nguồn')->options(fn () => Site::query()->orderBy('name')->pluck('domain', 'id')),
                SelectFilter::make('channel')->label('Kênh')->options(LeadChannel::class),
                SelectFilter::make('status')->label('Trạng thái')->options(LeadStatus::class),
                SelectFilter::make('assigned_to')->label('Phụ trách')->options(fn () => User::salesPeople()->pluck('name', 'id')),
                Filter::make('unassigned')->label('Chưa giao')->query(fn (Builder $query) => $query->whereNull('assigned_to')),
            ])
            ->recordActions([
                EditAction::make()->label('Xem'),
                ActionGroup::make([
                    self::assignAction(),
                    Action::make('convert')->label('Chuyển thành khách hàng')->icon(Heroicon::OutlinedBuildingOffice2)
                        ->visible(fn (Lead $record): bool => $record->customer_id === null)
                        ->action(function (Lead $record): void {
                            $customer = CustomerMatcher::convert($record);
                            Notification::make()->title('Đã tạo/gắn khách hàng: '.$customer->name)->success()->send();
                        }),
                    Action::make('quote')->label('Tạo báo giá')->icon(Heroicon::OutlinedDocumentText)
                        ->action(function (Lead $record) {
                            $customer = CustomerMatcher::convert($record);
                            $record->refresh();
                            $quote = Quote::query()->create([
                                'customer_id' => $customer->id, 'contact_id' => $record->contact_id, 'lead_id' => $record->id,
                                'site_id' => $record->site_id, 'owner_id' => $record->assigned_to ?? CrmFields::user()->id,
                                'notes' => 'Nhu cầu: '.$record->need,
                            ]);
                            if ($record->status !== LeadStatus::Won) {
                                $record->forceFill(['status' => LeadStatus::Quoted])->saveQuietly();
                            }

                            return redirect(QuoteResource::getUrl('edit', ['record' => $quote]));
                        }),
                    self::logActivityAction(),
                ])->label('Thao tác')->button()->size('sm')->color('gray'),
            ]);
    }

    public static function assignAction(): Action
    {
        return Action::make('assign')->label('Giao cho')->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (): bool => CrmFields::user()->isSalesManager())
            ->schema([Select::make('assigned_to')->label('Nhân viên')->options(fn () => User::salesPeople()->pluck('name', 'id'))->required()])
            ->fillForm(fn (Lead $record): array => ['assigned_to' => $record->assigned_to])
            ->action(function (Lead $record, array $data): void {
                $record->forceFill(['assigned_to' => (int) $data['assigned_to'], 'assigned_at' => now()])->save();
                if ($record->customer && $record->customer->owner_id === null) {
                    $record->customer->forceFill(['owner_id' => $record->assigned_to])->save();
                }
                $user = User::query()->find($record->assigned_to);
                if ($user && $user->id !== CrmFields::user()->id) {
                    CrmNotifier::leadAssigned($record, $user);
                }
                Notification::make()->title('Đã giao cho '.$user?->name)->success()->send();
            });
    }

    public static function logActivityAction(): Action
    {
        return Action::make('activity')->label('Ghi hoạt động')->icon(Heroicon::OutlinedPhone)
            ->schema([
                Select::make('type')->label('Loại')->options(ActivityType::class)->default(ActivityType::Call)->required(),
                Textarea::make('content')->label('Nội dung')->required()->rows(3),
            ])
            ->action(function (Lead $record, array $data): void {
                Activity::query()->create(['lead_id' => $record->id, 'customer_id' => $record->customer_id, ...$data]);
                Notification::make()->title('Đã ghi hoạt động')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'create' => CreateLead::route('/create'),
            'edit' => EditLead::route('/{record}/edit'),
        ];
    }
}
