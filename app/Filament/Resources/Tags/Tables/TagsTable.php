<?php

namespace App\Filament\Resources\Tags\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Tên')->searchable(),
                TextColumn::make('slug')->label('Slug'),
                TextColumn::make('posts_count')->label('Số bài')->counts('posts'),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }
}
