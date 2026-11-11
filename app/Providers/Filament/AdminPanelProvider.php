<?php

namespace App\Providers\Filament;

use App\Filament\Pages\SiteSettings;
use App\Http\Middleware\UseTenantAsCurrentSite;
use App\Models\Site;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->domain(config('sites.admin_domain'))
            ->path('admin')
            ->brandName('D&T – Quản trị page vệ tinh')
            ->login()
            ->tenant(Site::class, slugAttribute: 'domain')
            ->tenantProfile(SiteSettings::class)
            ->tenantMiddleware([UseTenantAsCurrentSite::class], isPersistent: true)
            ->colors([
                'primary' => Color::Amber,
            ])
            // Vùng quản lý của từng site: chọn site ở góc trái, mọi mục dưới đây là của site đó.
            ->navigationGroups([
                NavigationGroup::make('Nội dung'),
                NavigationGroup::make('Sản phẩm & bán hàng'),
                NavigationGroup::make('SEO & báo cáo'),
                NavigationGroup::make('Cài đặt site'),
                NavigationGroup::make('Hệ thống')->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('Cài đặt site')
                    ->group('Cài đặt site')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (): string => SiteSettings::getUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs(SiteSettings::getRouteName()))
                    ->visible(fn (): bool => Filament::getTenant() !== null && SiteSettings::canView(Filament::getTenant())),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
