<?php

namespace App\Filament\Resources\RequiredPages\Tables;

use App\Models\RequiredPage;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RequiredPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')->label('Loại'),
                TextColumn::make('title')->label('Tiêu đề'),
                TextColumn::make('path')->label('Đường dẫn')->state(fn (RequiredPage $record): string => $record->path()),
                IconColumn::make('is_published')->label('Hiển thị')->boolean(),
                TextColumn::make('updated_at')->label('Cập nhật')->since(),
            ])
            ->paginated(false)
            ->recordActions([EditAction::make()]);
    }
}
