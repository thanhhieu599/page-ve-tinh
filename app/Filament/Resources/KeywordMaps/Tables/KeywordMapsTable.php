<?php

namespace App\Filament\Resources\KeywordMaps\Tables;

use App\Enums\KeywordIntent;
use App\Models\KeywordMap;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KeywordMapsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')->label('Từ khoá')->searchable()->sortable(),
                TextColumn::make('target_url')->label('Trang đích')->searchable()
                    ->url(fn (KeywordMap $record): string => $record->targetUrl(), shouldOpenInNewTab: true),
                TextColumn::make('intent')->label('Mục đích')->badge(),
                TextColumn::make('updated_at')->label('Cập nhật')->since(),
            ])
            ->filters([SelectFilter::make('intent')->label('Mục đích')->options(KeywordIntent::class)])
            ->defaultSort('keyword')
            ->recordActions([EditAction::make()]);
    }
}
