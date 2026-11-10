<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Tên')->searchable(),
                TextColumn::make('parent.name')->label('Danh mục cha')->placeholder('—'),
                TextColumn::make('slug')->label('Slug'),
                TextColumn::make('posts_count')->label('Số bài')->counts('posts'),
                IconColumn::make('requires_expert_review')->label('Duyệt chuyên môn')->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
