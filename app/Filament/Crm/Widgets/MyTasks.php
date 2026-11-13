<?php

namespace App\Filament\Crm\Widgets;

use App\Filament\Crm\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class MyTasks extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Việc của tôi hôm nay';

    public function table(Table $table): Table
    {
        return $table
            ->query(Task::query()->where('assigned_to', auth()->id())->dueToday()->with('customer'))
            ->defaultSort('due_at')
            ->paginated([5])
            ->emptyStateHeading('Không có việc đến hạn')
            ->columns([
                TextColumn::make('due_at')->label('Hạn')->dateTime('d/m H:i', config('app.display_timezone'))
                    ->color(fn (Task $record): ?string => $record->due_at->isPast() ? 'danger' : null),
                TextColumn::make('title')->label('Việc')->wrap(),
                TextColumn::make('customer.name')->label('Khách')->placeholder('—'),
            ])
            ->recordActions([Action::make('done')->label('Xong')->icon(Heroicon::OutlinedCheck)->color('success')
                ->action(fn (Task $record) => $record->forceFill(['done_at' => now()])->save())])
            ->headerActions([Action::make('all')->label('Tất cả việc')->url(TaskResource::getUrl())]);
    }
}
