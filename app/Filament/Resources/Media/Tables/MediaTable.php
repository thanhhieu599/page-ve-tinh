<?php

namespace App\Filament\Resources\Media\Tables;

use App\Models\Media;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumbnail')->label('Ảnh')
                    ->state(fn (Media $record): string => $record->url($record->variantWidths()[0] ?? null)),
                TextColumn::make('alt')->label('Mô tả (alt)')->searchable()->wrap(),
                TextColumn::make('dimensions')->label('Kích thước')
                    ->state(fn (Media $record): string => $record->width.' × '.$record->height),
                TextColumn::make('created_at')->label('Tải lên')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
