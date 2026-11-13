<?php

namespace App\Filament\Crm\Resources\Customers\RelationManagers;

use App\Enums\Crm\ActivityType;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Lịch sử chăm sóc khách: gọi, email, Zalo, gặp mặt, ghi chú. */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Hoạt động';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('type')->label('Loại')->options(ActivityType::class)->default(ActivityType::Call)->required(),
            DateTimePicker::make('happened_at')->label('Thời điểm')->timezone(config('app.display_timezone'))->default(now())->required()->native(false)->displayFormat('d/m/Y H:i'),
            Textarea::make('content')->label('Nội dung')->required()->rows(3)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('happened_at', 'desc')->columns([
            TextColumn::make('happened_at')->label('Thời điểm')->dateTime('d/m/Y H:i', config('app.display_timezone')),
            TextColumn::make('type')->label('Loại')->badge(),
            TextColumn::make('content')->label('Nội dung')->wrap()->limit(200),
            TextColumn::make('user.name')->label('Người ghi'),
        ])->headerActions([CreateAction::make()->label('Ghi hoạt động')]);
    }
}
