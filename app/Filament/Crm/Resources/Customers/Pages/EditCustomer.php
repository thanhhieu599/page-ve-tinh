<?php

namespace App\Filament\Crm\Resources\Customers\Pages;

use App\Filament\Crm\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;
}
