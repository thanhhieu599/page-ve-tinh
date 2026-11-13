<?php

namespace App\Filament\Crm\Resources\Leads\Pages;

use App\Filament\Crm\Resources\Leads\LeadResource;
use Filament\Resources\Pages\EditRecord;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [LeadResource::assignAction()->record($this->getRecord()), LeadResource::logActivityAction()->record($this->getRecord())];
    }
}
