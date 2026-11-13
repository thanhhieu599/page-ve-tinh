<?php

namespace App\Providers\Filament;

use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Middleware\AddSiteNavigationToCrm;
use App\Models\Site;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * CRM chung cho mọi site vệ tinh (không chọn site): lead từ mọi site, khách hàng doanh nghiệp,
 * báo giá, đơn hàng/công nợ, việc cần làm, chăm sóc tái đặt hàng, báo cáo, xuất MISA.
 * Cùng đăng nhập với trang quản trị nội dung; vai trò quyết định vào được khu nào.
 */
class CrmPanelProvider extends PanelProvider
{
    public const PRIMARY = [
        50 => '#fef2f2', 100 => '#fde3e3', 200 => '#fbcccd', 300 => '#f6a5a7', 400 => '#ec6f73',
        500 => '#dc3f44', 600 => '#c1272d', 700 => '#9b1d22', 800 => '#811c20', 900 => '#6b1c1f', 950 => '#3a0a0c',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('crm')
            ->domain(config('sites.admin_domain'))
            ->path('crm')
            ->brandName('D&T – CRM bán hàng')
            ->login()
            ->darkMode(false)
            ->colors(['primary' => self::PRIMARY, 'gray' => Color::Zinc])
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->databaseNotifications()
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Tổng quan'),
                NavigationGroup::make('Bán hàng'),
                NavigationGroup::make('Sản phẩm'),
                NavigationGroup::make('Nội dung website'),
                NavigationGroup::make('Phân tích'),
                NavigationGroup::make('Cấu hình'),
            ])
            ->navigationItems([
                NavigationItem::make('Tất cả site (trang quản trị)')
                    ->group('Nội dung website')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->sort(99)
                    ->url(fn (): string => '/admin')
                    ->visible(fn (): bool => (bool) auth()->user()?->canAccessAdmin()),
                NavigationItem::make('Người dùng và phân quyền')
                    ->group('Cấu hình')
                    ->icon(Heroicon::OutlinedUsers)
                    ->sort(9)
                    ->url(fn (): string => ($site = Site::query()->orderBy('name')->first()) ? '/admin/'.$site->domain.'/users' : '/admin')
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
            ])
            ->discoverResources(in: app_path('Filament/Crm/Resources'), for: 'App\Filament\Crm\Resources')
            ->discoverPages(in: app_path('Filament/Crm/Pages'), for: 'App\Filament\Crm\Pages')
            ->discoverWidgets(in: app_path('Filament/Crm/Widgets'), for: 'App\Filament\Crm\Widgets')
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
                // Mỗi site vệ tinh một mục menu dẫn tới phần quản trị nội dung của site đó.
                AddSiteNavigationToCrm::class,
            ]);
    }
}
