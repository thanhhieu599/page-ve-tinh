<?php

namespace App\Providers\Filament;

use App\Filament\Pages\SiteSettings;
use App\Filament\Support\InitialsAvatarProvider;
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
            // Giao diện sáng, màu đỏ thương hiệu D&T (không theo chế độ tối của máy tính).
            ->darkMode(false)
            ->colors([
                // Bảng màu đỏ D&T: sắc 600 = đỏ thương hiệu #c1272d để nút chính nền đỏ đậm, chữ trắng.
                'primary' => [
                    50 => '#fef2f2', 100 => '#fde3e3', 200 => '#fbcccd', 300 => '#f6a5a7', 400 => '#ec6f73',
                    500 => '#dc3f44', 600 => '#c1272d', 700 => '#9b1d22', 800 => '#811c20', 900 => '#6b1c1f', 950 => '#3a0a0c',
                ],
                'gray' => Color::Zinc,
            ])
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // Vùng quản lý của từng site: chọn site ở góc trái, mọi mục dưới đây là của site đó.
            ->navigationGroups([
                NavigationGroup::make('Nội dung'),
                NavigationGroup::make('Sản phẩm & bán hàng'),
                NavigationGroup::make('SEO & báo cáo'),
                NavigationGroup::make('Cài đặt site'),
                NavigationGroup::make('Hệ thống')->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('CRM bán hàng (mọi site)')
                    ->group('Sản phẩm & bán hàng')
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->sort(99)
                    ->url(fn (): string => '/crm')
                    ->visible(fn (): bool => (bool) auth()->user()?->canAccessCrm()),
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
