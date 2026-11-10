<?php

namespace App\Filament\Resources\KeywordMaps\Pages;

use App\Filament\Resources\KeywordMaps\KeywordMapResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKeywordMap extends EditRecord
{
    protected static string $resource = KeywordMapResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
