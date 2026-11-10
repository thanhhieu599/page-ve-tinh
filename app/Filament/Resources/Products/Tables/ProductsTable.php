<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\PriceStatus;
use App\Models\Product;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Tên')->searchable()->wrap(),
                TextColumn::make('sku')->label('SKU')->searchable(),
                TextColumn::make('price')->label('Giá')
                    ->formatStateUsing(fn (Product $record): string => $record->formattedPrice())
                    ->placeholder('—'),
                TextColumn::make('price_status')->label('Giá trên site')->badge()
                    ->state(fn (Product $record): string => match ($record->priceStatus()) {
                        PriceStatus::None => 'Chưa có giá',
                        PriceStatus::Expired => 'Hết hạn, đang ẩn',
                        default => 'Hiện đến hết '.$record->priceShownUntil()?->format('d/m'),
                    })
                    ->color(fn (Product $record): string => $record->priceStatus()->color()),
                IconColumn::make('is_active')->label('Hiện')->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }
}
