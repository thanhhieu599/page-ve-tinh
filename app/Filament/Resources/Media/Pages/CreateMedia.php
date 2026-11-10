<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use App\Filament\Support\TenantSite;
use App\Support\MediaLibrary;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        $file = $data['file'];
        assert($file instanceof TemporaryUploadedFile);

        return MediaLibrary::store($file->getRealPath(), $data['alt'], (int) TenantSite::id());
    }
}
