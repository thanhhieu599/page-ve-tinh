<?php

namespace App\Filament\Crm\Resources\Customers\Pages;

use App\Filament\Crm\Resources\Customers\CustomerResource;
use App\Filament\Crm\Support\CrmFields;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_id'] ??= CrmFields::user()->id;

        return $data;
    }
}
