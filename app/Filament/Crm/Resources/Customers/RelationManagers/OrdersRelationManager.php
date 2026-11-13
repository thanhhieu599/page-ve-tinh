<?php

namespace App\Filament\Crm\Resources\Customers\RelationManagers;

use App\Filament\Crm\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Đơn hàng của khách (bấm để mở chi tiết). */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Đơn hàng';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('number')->label('Số'),
            TextColumn::make('created_at')->label('Ngày')->date('d/m/Y'),
            TextColumn::make('status')->label('Trạng thái')->badge(),
            TextColumn::make('total')->label('Tổng tiền')->money('VND', locale: 'vi'),
            TextColumn::make('owner.name')->label('Phụ trách'),
        ])
            ->recordUrl(fn ($record): string => OrderResource::getUrl('edit', ['record' => $record]))
            ->headerActions([Action::make('new')->label('Tạo đơn hàng')->url(fn (): string => OrderResource::getUrl('create', ['customer' => $this->getOwnerRecord()->getKey()]))]);
    }
}
