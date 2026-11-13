<?php

namespace App\Http\Middleware;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menu CRM → "Nội dung website": mỗi site đang hoạt động một mục (bài viết, sản phẩm, danh mục,
 * banner & cài đặt site, trang tĩnh, xem website). Site mới tự hiện; chỉ người quản trị nội dung thấy.
 */
class AddSiteNavigationToCrm
{
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentPanel();
        $user = $request->user();

        if ($panel !== null && $user instanceof User) {
            $existing = array_map(fn (NavigationItem $item) => $item->getLabel(), $panel->getNavigationItems());
            $items = [];
            foreach (Site::query()->where('is_active', true)->orderBy('name')->get() as $site) {
                if (! in_array($site->name, $existing, true)) {
                    $items[] = $this->item($site);
                }
            }
            $panel->navigationItems($items);
        }

        return $next($request);
    }

    /** Người đang xem quản trị được nội dung site này (Admin: mọi site; Biên tập/Người viết: site được gán). */
    private static function canManage(Site $site): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canAccessAdmin() && $user->canAccessTenant($site);
    }

    private function item(Site $site): NavigationItem
    {
        $admin = '/admin/'.$site->domain;
        $url = fn (string $path = ''): string => url($admin.$path);
        $newLeads = Lead::withoutGlobalScopes()->where('site_id', $site->id)->where('status', LeadStatus::New)->whereNull('assigned_to')->count();

        return NavigationItem::make($site->name)
            ->group('Nội dung website')
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->visible(fn (): bool => self::canManage($site))
            // Không đặt link cho mục cha: menu con (bài viết, sản phẩm…) luôn mở sẵn.
            ->badge($newLeads > 0 ? (string) $newLeads : null)
            ->childItems([
                NavigationItem::make('Tổng quan · '.$site->name)->label('Tổng quan site')->url($url()),
                NavigationItem::make('Bài viết · '.$site->name)->label('Bài viết')->url($url('/posts')),
                NavigationItem::make('Sản phẩm · '.$site->name)->label('Sản phẩm')->url($url('/products')),
                NavigationItem::make('Danh mục · '.$site->name)->label('Danh mục')->url($url('/categories')),
                NavigationItem::make('Banner · '.$site->name)->label('Banner & cài đặt site')->url($url('/cai-dat-site')),
                NavigationItem::make('Trang tĩnh · '.$site->name)->label('Giới thiệu, chính sách')->url($url('/required-pages')),
                NavigationItem::make('Xem · '.$site->name)->label('Xem website ↗')->url(rtrim($site->url('/'), '/'), shouldOpenInNewTab: true),
            ]);
    }
}
