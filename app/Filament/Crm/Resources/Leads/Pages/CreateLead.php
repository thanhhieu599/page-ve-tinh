<?php

namespace App\Filament\Crm\Resources\Leads\Pages;

use App\Filament\Crm\Resources\Leads\LeadResource;
use App\Filament\Crm\Support\CrmFields;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['assigned_to'] ??= CrmFields::user()->id;
        $data['assigned_at'] = now();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $lead = new (static::getModel());
        $lead->forceFill([
            ...$data,
            // Lead nhập tay: khách đồng ý qua kênh liên hệ trực tiếp; ghi rõ người nhập.
            'consent_text' => 'Khách cung cấp thông tin trực tiếp cho nhân viên '.CrmFields::user()->name.' qua kênh '.($data['channel'] ?? '').'.',
            'consent_at' => now(),
        ])->save();

        return $lead;
    }
}
