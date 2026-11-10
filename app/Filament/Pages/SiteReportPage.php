<?php

namespace App\Filament\Pages;

use App\Models\Site;
use App\Models\User;
use App\Support\Reports\SiteReport;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Báo cáo tổng hợp theo site: Search Console (hiển thị, nhấp, CTR, vị trí) và bán hàng
 * (lead, lead thắng, doanh thu đã chốt). Admin xem mọi site, Biên tập xem site được gán.
 */
class SiteReportPage extends Page
{
    protected static ?string $slug = 'bao-cao';

    protected static ?string $title = 'Báo cáo theo site';

    protected static ?string $navigationLabel = 'Báo cáo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.pages.site-report';

    /** Khoảng tối đa để báo cáo không quét quá nhiều dữ liệu. */
    private const MAX_DAYS = 366;

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isReviewer();
    }

    public function report(): SiteReport
    {
        $default = SiteReport::defaultPeriod();
        $from = self::date($this->from) ?? $default->from;
        $to = self::date($this->to) ?? $default->to;

        if ($from->greaterThan($to) || $from->diffInDays($to) >= self::MAX_DAYS) {
            return $default;
        }

        return new SiteReport($from, $to);
    }

    /** @return Collection<int, Site> */
    public function sites(): Collection
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user->isAdmin() ? Site::query()->orderBy('name')->get() : $user->sites()->orderBy('name')->get();
    }

    private static function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, (string) config('app.display_timezone'))?->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
