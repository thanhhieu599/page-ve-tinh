<?php

namespace App\Filament\Crm\Resources\Customers\RelationManagers;

use App\Filament\Crm\Resources\Tasks\TaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/** Việc cần làm với khách này (dùng chung form/bảng với mục Việc cần làm). */
class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Việc cần làm';

    public function form(Schema $schema): Schema
    {
        return TaskResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return TaskResource::table($table)->headerActions([CreateAction::make()->label('Thêm việc')]);
    }
}
