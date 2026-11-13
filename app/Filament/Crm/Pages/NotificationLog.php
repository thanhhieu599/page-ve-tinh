<?php

namespace App\Filament\Crm\Pages;

use App\Filament\Crm\Support\CrmFields;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Notifications\DatabaseNotification;

/** Nhật ký thông báo CRM (giao lead, giao việc, nhắc tái đặt hàng): trưởng phòng xem tất cả, nhân viên xem của mình. */
class NotificationLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'nhat-ky-thong-bao';

    protected static ?string $title = 'Nhật ký thông báo';

    protected static string|\UnitEnum|null $navigationGroup = 'Phân tích';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected string $view = 'filament.crm.table-page';

    public function table(Table $table): Table
    {
        $user = CrmFields::user();

        return $table
            ->query(DatabaseNotification::query()->where('notifiable_type', User::class)
                ->when(! $user->isSalesManager(), fn ($query) => $query->where('notifiable_id', $user->id)))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Thời điểm')->dateTime('d/m/Y H:i', config('app.display_timezone')),
                TextColumn::make('notifiable_id')->label('Người nhận')
                    ->formatStateUsing(fn ($state): string => User::query()->find($state)?->name ?? '—'),
                TextColumn::make('title')->label('Thông báo')->state(fn (DatabaseNotification $record): string => (string) ($record->data['title'] ?? ''))->wrap(),
                TextColumn::make('body')->label('Nội dung')->state(fn (DatabaseNotification $record): string => (string) ($record->data['body'] ?? ''))->wrap()->limit(160),
                IconColumn::make('read')->label('Đã đọc')->state(fn (DatabaseNotification $record): bool => $record->read_at !== null)->boolean(),
            ])
            ->filters([
                SelectFilter::make('notifiable_id')->label('Người nhận')->options(fn () => User::salesPeople()->pluck('name', 'id'))
                    ->visible($user->isSalesManager()),
            ]);
    }
}
