<?php

namespace App\Filament\Pages;

use App\Models\Site;
use App\Models\User;
use App\Support\Indexing\IndexReadiness;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Công tắc index của site đang chọn: checklist điều kiện (ai trong ban biên tập cũng xem
 * được), chỉ Admin bật/tắt, và chỉ bật được khi mọi điều kiện đã đạt.
 */
class SearchIndexing extends Page
{
    protected static ?string $slug = 'mo-index';

    protected static ?string $title = 'Mở index cho Google';

    protected static ?string $navigationLabel = 'Mở index (SEO)';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.search-indexing';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isReviewer();
    }

    public function site(): Site
    {
        $site = Filament::getTenant();
        assert($site instanceof Site);

        return $site;
    }

    public function readiness(): IndexReadiness
    {
        return IndexReadiness::for($this->site());
    }

    private static function isAdmin(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enableIndexing')
                ->label('Bật index')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (): bool => self::isAdmin() && ! $this->site()->is_indexable)
                ->disabled(fn (): bool => ! $this->readiness()->isReady())
                ->tooltip(fn (): ?string => $this->readiness()->isReady() ? null : 'Còn điều kiện chưa đạt trong checklist')
                ->requiresConfirmation()
                ->modalDescription('Google sẽ được mời thu thập và index toàn bộ site. Tiếp tục?')
                ->action(function (): void {
                    $this->site()->enableIndexing();
                    Notification::make()->title('Đã bật index')->success()->send();
                }),

            Action::make('disableIndexing')
                ->label('Tắt index')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn (): bool => self::isAdmin() && $this->site()->is_indexable)
                ->requiresConfirmation()
                ->modalDescription('Mọi trang sẽ gắn noindex và robots.txt chặn thu thập. Thứ hạng hiện có sẽ mất dần.')
                ->action(function (): void {
                    $this->site()->disableIndexing();
                    Notification::make()->title('Đã tắt index')->warning()->send();
                }),
        ];
    }
}
