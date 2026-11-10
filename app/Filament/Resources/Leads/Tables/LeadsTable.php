<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\LeadStatus;
use App\Jobs\SyncLeadToMautic;
use App\Models\Lead;
use App\Support\Mautic\MauticClient;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Nhận lúc')
                    ->dateTime('d/m/Y H:i', config('app.display_timezone'))->sortable(),
                TextColumn::make('name')->label('Họ tên')->searchable(),
                TextColumn::make('company')->label('Công ty')->searchable()->wrap(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('phone')->label('Điện thoại'),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('utm_source')->label('Nguồn')->placeholder('—'),
                TextColumn::make('utm_medium')->label('Kênh')->placeholder('—')->toggleable(),
                TextColumn::make('utm_campaign')->label('Chiến dịch')->placeholder('—')->toggleable(),
                TextColumn::make('landing_url')->label('Trang vào đầu')->limit(40)->toggleable(),
                IconColumn::make('is_verified')->label('Xác minh')->boolean(),
                IconColumn::make('synced_to_mautic_at')->label('Mautic')
                    ->state(fn (Lead $record): bool => $record->isSyncedToMautic())->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(LeadStatus::class),
                TernaryFilter::make('mautic')->label('Mautic')
                    ->trueLabel('Đã đồng bộ')->falseLabel('Chưa đồng bộ')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('synced_to_mautic_at'),
                        false: fn (Builder $query) => $query->whereNull('synced_to_mautic_at'),
                    ),
            ])
            ->recordActions([
                EditAction::make()->label('Xem / cập nhật'),
                Action::make('resyncMautic')->label('Đồng bộ lại Mautic')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Lead $record): bool => ! $record->isSyncedToMautic())
                    ->authorize('update')
                    ->action(function (Lead $record): void {
                        if (! MauticClient::configured()) {
                            Notification::make()->title('Mautic chưa được cấu hình')
                                ->body('Cần đặt MAUTIC_URL, MAUTIC_USERNAME, MAUTIC_PASSWORD trên máy chủ rồi thử lại.')
                                ->warning()->send();

                            return;
                        }

                        // Job là duy nhất theo lead: bấm nhiều lần không tạo thêm job.
                        SyncLeadToMautic::dispatch($record->id);
                        Notification::make()->title('Đã đưa lead vào hàng đợi đồng bộ Mautic')->success()->send();
                    }),
            ]);
    }
}
