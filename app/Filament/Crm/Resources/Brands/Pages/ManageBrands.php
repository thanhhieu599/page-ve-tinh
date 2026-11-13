<?php

namespace App\Filament\Crm\Resources\Brands\Pages;

use App\Filament\Crm\Resources\Brands\BrandResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBrands extends ManageRecords
{
    protected static string $resource = BrandResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
