<?php

namespace Tests\Concerns;

use App\Models\Site;
use App\Models\User;
use App\Support\CurrentSite;
use Filament\Facades\Filament;

/**
 * Đăng nhập trang quản trị và chọn một site, như người dùng thật làm.
 */
trait WorksInAdmin
{
    protected function workOn(Site $site, User $user): void
    {
        $this->actingAs($user);
        Filament::setTenant($site);
        Filament::bootCurrentPanel();

        // Giống middleware UseTenantAsCurrentSite chạy trong mọi request của trang quản trị.
        app(CurrentSite::class)->set($site);
    }
}
