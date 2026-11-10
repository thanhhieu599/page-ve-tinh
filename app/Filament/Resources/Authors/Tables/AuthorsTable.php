<?php

namespace App\Filament\Resources\Authors\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuthorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Họ tên')->searchable(),
                TextColumn::make('job_title')->label('Chức danh'),
                TextColumn::make('slug')->label('Slug'),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }
}
