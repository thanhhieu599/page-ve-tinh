<?php

namespace App\Filament\Crm\Pages;

use App\Enums\Crm\TaskType;
use App\Filament\Crm\Resources\Customers\CustomerResource;
use App\Filament\Crm\Resources\Quotes\QuoteResource;
use App\Filament\Crm\Support\CrmFields;
use App\Models\Customer;
use App\Models\Order;
use App\Support\Crm\ReorderReminders;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Chăm sóc & tái đặt hàng: khách sắp đến kỳ mua lại (PPE, vật tư tiêu hao dùng theo chu kỳ).
 * Một bấm: báo giá mới theo đơn gần nhất, hoặc tạo việc gọi chăm sóc.
 */
class Care extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'cham-soc';

    protected static ?string $title = 'Chăm sóc & tái đặt hàng';

    protected static string|\UnitEnum|null $navigationGroup = 'Bán hàng';

    protected static ?int $navigationSort = 7;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected string $view = 'filament.crm.table-page';

    public static function getNavigationBadge(): ?string
    {
        $count = auth()->check() ? Customer::query()->visibleTo(CrmFields::user())->whereNotNull('next_reorder_at')
            ->whereDate('next_reorder_at', '<=', now()->addDays(ReorderReminders::DAYS_AHEAD))->count() : 0;

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Customer::query()->visibleTo(CrmFields::user())->whereNotNull('next_reorder_at')->with('owner'))
            ->defaultSort('next_reorder_at')
            ->columns([
                TextColumn::make('next_reorder_at')->label('Đến kỳ đặt lại')->date('d/m/Y')->sortable()
                    ->color(fn (Customer $record): ?string => $record->next_reorder_at->isPast() ? 'danger' : ($record->next_reorder_at->lte(now()->addDays(ReorderReminders::DAYS_AHEAD)) ? 'warning' : null)),
                TextColumn::make('name')->label('Khách hàng')->wrap()->searchable()
                    ->url(fn (Customer $record): string => CustomerResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('last_order')->label('Đơn gần nhất')
                    ->state(fn (Customer $record): string => ($o = $record->orders()->latest('ordered_at')->first()) ? $o->number.' · '.$o->ordered_at->format('d/m/Y').' · '.Order::money($o->total) : '—'),
                TextColumn::make('reorder_cycle_days')->label('Chu kỳ')->suffix(' ngày')->placeholder('Mặc định'),
                TextColumn::make('owner.name')->label('Phụ trách'),
            ])
            ->recordActions([
                Action::make('requote')->label('Báo giá lại theo đơn gần nhất')->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->visible(fn (Customer $record): bool => $record->orders()->exists())
                    ->action(fn (Customer $record) => redirect(QuoteResource::getUrl('edit', ['record' => ReorderReminders::requote($record)]))),
                Action::make('task')->label('Tạo việc gọi chăm sóc')->icon(Heroicon::OutlinedPhone)
                    ->action(function (Customer $record): void {
                        ReorderReminders::taskFor($record, TaskType::Care, 'Gọi chăm sóc, hỏi nhu cầu đặt lại: '.$record->name);
                        Notification::make()->title('Đã tạo việc')->success()->send();
                    }),
            ]);
    }
}
