<?php

namespace App\Support\Reports;

use App\Enums\LeadStatus;
use App\Jobs\PullSearchConsoleData;
use App\Models\Lead;
use App\Models\Scopes\SiteScope;
use App\Models\Site;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Báo cáo tổng hợp theo site cho ban giám đốc: số liệu Search Console và kết quả bán hàng
 * (lead, lead thắng, doanh thu đã chốt) trong cùng một khoảng ngày (giờ Việt Nam).
 */
final class SiteReport
{
    public const DEFAULT_DAYS = 28;

    public const TOP_PAGES = 10;

    public function __construct(public readonly CarbonImmutable $from, public readonly CarbonImmutable $to) {}

    /** 28 ngày gần nhất có số liệu Search Console (Google trễ khoảng 2 ngày). */
    public static function defaultPeriod(): self
    {
        $to = CarbonImmutable::now(self::timezone())->startOfDay()->subDays(PullSearchConsoleData::DELAY_DAYS);

        return new self($to->subDays(self::DEFAULT_DAYS - 1), $to);
    }

    /**
     * @param  iterable<Site>  $sites
     * @return list<SiteReportRow>
     */
    public function rows(iterable $sites): array
    {
        $sites = collect($sites)->keyBy('id');
        if ($sites->isEmpty()) {
            return [];
        }
        $ids = $sites->keys()->all();

        $search = DB::table('gsc_page_daily')
            ->whereIn('site_id', $ids)
            ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->groupBy('site_id')
            ->selectRaw('site_id, SUM(impressions) AS impressions, SUM(clicks) AS clicks, SUM(position * impressions) AS weighted_position')
            ->get()->keyBy('site_id');

        [$start, $end] = $this->utcBounds();
        $leads = $this->leads($ids)->whereBetween('created_at', [$start, $end])
            ->groupBy('site_id')->selectRaw('site_id, COUNT(*) AS total')->pluck('total', 'site_id');
        $won = $this->leads($ids)->where('status', LeadStatus::Won)->whereBetween('won_at', [$start, $end])
            ->groupBy('site_id')->selectRaw('site_id, COUNT(*) AS total, COALESCE(SUM(deal_value), 0) AS revenue')
            ->get()->keyBy('site_id');

        return $sites->sortBy('name')->map(fn (Site $site): SiteReportRow => new SiteReportRow(
            site: $site,
            impressions: (int) ($search[$site->id]->impressions ?? 0),
            clicks: (int) ($search[$site->id]->clicks ?? 0),
            weightedPosition: (float) ($search[$site->id]->weighted_position ?? 0),
            leads: (int) ($leads[$site->id] ?? 0),
            wonLeads: (int) ($won[$site->id]->total ?? 0),
            revenue: (int) ($won[$site->id]->revenue ?? 0),
        ))->values()->all();
    }

    /** @return Collection<int, object{page_url: string, clicks: int, impressions: int}> trang nhiều lượt nhấp nhất */
    public function topPages(Site $site): Collection
    {
        return DB::table('gsc_page_daily')
            ->where('site_id', $site->id)
            ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->groupBy('page_url')
            ->selectRaw('page_url, SUM(clicks) AS clicks, SUM(impressions) AS impressions')
            ->orderByDesc('clicks')->orderBy('page_url')
            ->limit(self::TOP_PAGES)
            ->get();
    }

    /** @param  list<int>  $siteIds */
    private function leads(array $siteIds): Builder
    {
        return Lead::withoutGlobalScope(SiteScope::class)->whereIn('site_id', $siteIds);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} đầu ngày "từ" và cuối ngày "đến" giờ Việt Nam, quy về UTC */
    private function utcBounds(): array
    {
        return [
            $this->from->setTimezone(self::timezone())->startOfDay()->utc(),
            $this->to->setTimezone(self::timezone())->endOfDay()->utc(),
        ];
    }

    private static function timezone(): string
    {
        return (string) config('app.display_timezone');
    }
}
