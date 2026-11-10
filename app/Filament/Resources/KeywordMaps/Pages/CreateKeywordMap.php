<?php

namespace App\Filament\Resources\KeywordMaps\Pages;

use App\Filament\Resources\KeywordMaps\KeywordMapResource;
use App\Filament\Resources\KeywordMaps\Schemas\KeywordMapForm;
use App\Filament\Support\TenantSite;
use App\Models\KeywordMap;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

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
        $site = TenantSite::site();
        assert($site !== null);

        $owner = KeywordMap::ownerOf((string) $data['keyword']);
        $override = KeywordMapForm::canReassign() && (bool) ($this->data['override'] ?? false);

        $keywordMap = $owner !== null && $override ? $owner : new KeywordMap;
        $keywordMap->fill($data);
        $keywordMap->site()->associate($site);

        try {
            $keywordMap->save();
        } catch (UniqueConstraintViolationException) {
            // Hai người gán cùng từ khoá cùng lúc: người sau nhận cảnh báo như thường.
            throw ValidationException::withMessages(['data.keyword' => 'Từ khoá này vừa được gán cho trang khác. Tải lại để xem trang đang giữ.']);
        }

        return $keywordMap;
    }
}
