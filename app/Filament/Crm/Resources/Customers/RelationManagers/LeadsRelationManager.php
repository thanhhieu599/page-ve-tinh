<?php

namespace App\Filament\Crm\Resources\Customers\RelationManagers;

use App\Filament\Crm\Resources\Leads\LeadResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Mọi yêu cầu báo giá của khách, từ mọi site. */
class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    protected static ?string $title = 'Yêu cầu từ các site';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('created_at')->label('Nhận lúc')->dateTime('d/m/Y H:i', config('app.display_timezone')),
            TextColumn::make('site.domain')->label('Site')->placeholder('Nhập tay')->badge()->color('gray'),
            TextColumn::make('name')->label('Người gửi'),
            TextColumn::make('need')->label('Nhu cầu')->limit(80)->wrap(),
            TextColumn::make('status')->label('Trạng thái')->badge(),
        ])->recordUrl(fn ($record): string => LeadResource::getUrl('edit', ['record' => $record]));
    }
}
