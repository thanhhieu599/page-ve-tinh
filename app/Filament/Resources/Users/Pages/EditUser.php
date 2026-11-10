<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->getRecord();
        $data['role'] = $user instanceof User ? $user->getRoleNames()->first() : null;
        $data['expert_reviewer'] = $user instanceof User && $user->hasDirectPermission(Permission::ApproveExpertContent->value);

        return $data;
    }

    protected function afterSave(): void
    {
        $user = $this->getRecord();

        if ($user instanceof User) {
            $user->syncAdminAccess($this->data['role'], (bool) ($this->data['expert_reviewer'] ?? false));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
