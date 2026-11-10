<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $user = $this->getRecord();

        if ($user instanceof User) {
            $user->syncAdminAccess($this->data['role'], (bool) ($this->data['expert_reviewer'] ?? false));
        }
    }
}
