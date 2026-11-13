<?php

namespace App\Filament\Crm\Resources\Leads\Pages;

use App\Filament\Crm\Resources\Leads\LeadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Thêm lead (điện thoại, Zalo, hội chợ…)')];
    }
}
