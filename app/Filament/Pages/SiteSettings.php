<?php

namespace App\Filament\Pages;

use App\Filament\Support\SiteSettingsSchema;
use App\Models\Site;
use App\Models\User;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Panel;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * "Cài đặt site": vùng quản lý riêng của từng site (nhận diện, banner trang chủ, đo lường),
 * mở từ menu chọn site hoặc mục "Cài đặt site". Biên tập và Admin; mã đo lường chỉ Admin.
 */
class SiteSettings extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Cài đặt site';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'cai-dat-site';
    }

    public static function canView(Model $tenant): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isReviewer() && $user->canAccessTenant($tenant);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(SiteSettingsSchema::sections(self::canEditIntegrations()));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $site = $this->tenant;
        assert($site instanceof Site);

        return SiteSettingsSchema::mergeSettings($site, $data);
    }

    private static function canEditIntegrations(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }
}
