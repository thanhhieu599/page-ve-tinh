<?php

namespace App\Filament\Resources\KeywordMaps\Pages;

use App\Filament\Resources\KeywordMaps\KeywordMapResource;
use App\Filament\Resources\KeywordMaps\Schemas\KeywordMapForm;
use App\Models\KeywordMap;
use App\Models\Site;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateKeywordMap extends CreateRecord
{
    protected static string $resource = KeywordMapResource::class;

    /**
     * Admin ghi đè: chuyển dòng đang có sang trang này (vẫn một trang đích cho mỗi từ khoá).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $site = Filament::getTenant();
        assert($site instanceof Site);

        $owner = KeywordMap::ownerOf((string) $data['keyword']);
        $override = KeywordMapForm::isAdmin() && (bool) ($this->data['override'] ?? false);

        $keywordMap = $owner !== null && $override ? $owner : new KeywordMap;
        $keywordMap->fill($data);
        $keywordMap->site()->associate($site);
        $keywordMap->save();

        return $keywordMap;
    }
}
