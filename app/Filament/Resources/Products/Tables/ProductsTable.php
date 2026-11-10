<?php

namespace App\Filament\Resources\Products\Tables;

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
                    ->state(fn (Product $record): string => match (true) {
                        $record->price === null => 'Chưa có giá',
                        ! $record->hasFreshPrice() => 'Hết hạn, đang ẩn',
                        default => 'Hiện đến '.$record->priceExpiresAt()?->timezone(config('app.display_timezone'))->format('d/m'),
                    })
                    ->color(fn (Product $record): string => match (true) {
                        $record->price === null => 'gray',
                        ! $record->hasFreshPrice() => 'danger',
                        now()->diffInDays($record->priceExpiresAt(), true) <= Product::PRICE_WARNING_DAYS => 'warning',
                        default => 'success',
                    }),
                IconColumn::make('is_active')->label('Hiện')->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }
}
