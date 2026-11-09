<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsToMany<Site, $this> */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class)->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin->value);
    }

    /** Chỉ người có một trong ba vai trò mới vào được trang quản trị. */
    public function canAccessAdmin(): bool
    {
        return $this->hasAnyRole(Role::values());
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->canAccessAdmin();
    }

    /**
     * Site người dùng làm việc được: Admin thấy mọi site, người khác chỉ thấy site được gán.
     *
     * @return Collection<int, Site>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->isAdmin() ? Site::query()->orderBy('name')->get() : $this->sites()->orderBy('name')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->isAdmin() || $this->sites()->whereKey($tenant->getKey())->exists();
    }
}
