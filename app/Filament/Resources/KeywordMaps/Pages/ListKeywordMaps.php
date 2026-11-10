<?php

namespace App\Filament\Resources\KeywordMaps\Pages;

use App\Filament\Resources\KeywordMaps\KeywordMapResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKeywordMaps extends ListRecords
{
    protected static string $resource = KeywordMapResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
