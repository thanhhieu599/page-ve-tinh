<?php

namespace App\Filament\Crm\Resources\Tasks\Pages;

use App\Filament\Crm\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Support\Crm\CrmNotifier;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Thêm việc')->after(function (Task $record): void {
            $to = User::query()->find($record->assigned_to);
            if ($to && $to->id !== auth()->id()) {
                CrmNotifier::taskAssigned($record, $to);
            }
        })];
    }

    public function getTabs(): array
    {
        return [
            'today' => Tab::make('Hôm nay & quá hạn')->modifyQueryUsing(fn (Builder $query) => $query->dueToday()),
            'open' => Tab::make('Chưa xong')->modifyQueryUsing(fn (Builder $query) => $query->open()),
            'done' => Tab::make('Đã xong')->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('done_at')->reorder('done_at', 'desc')),
        ];
    }
}
