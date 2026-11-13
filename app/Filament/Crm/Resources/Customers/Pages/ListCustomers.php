<?php

namespace App\Filament\Crm\Resources\Customers\Pages;

use App\Filament\Crm\Resources\Customers\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
