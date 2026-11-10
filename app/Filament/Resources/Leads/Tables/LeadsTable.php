<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\LeadStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(LeadStatus::class),
            ])
            ->recordActions([EditAction::make()->label('Xem / cập nhật')]);
    }
}
