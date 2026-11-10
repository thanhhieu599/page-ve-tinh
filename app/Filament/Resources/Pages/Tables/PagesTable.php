<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Models\Page;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')->label('Loại'),
                TextColumn::make('title')->label('Tiêu đề'),
                TextColumn::make('path')->label('Đường dẫn')->state(fn (Page $record): string => $record->path()),
                IconColumn::make('is_published')->label('Hiển thị')->boolean(),
                TextColumn::make('updated_at')->label('Cập nhật')->since(),
            ])
            ->paginated(false)
            ->recordActions([EditAction::make()]);
    }
}
