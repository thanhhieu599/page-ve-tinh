<?php

namespace App\Filament\Crm\Resources\Orders\Pages;

use App\Filament\Crm\Resources\Orders\OrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
