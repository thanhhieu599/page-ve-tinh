<?php

namespace App\Support\Reports;

use App\Enums\LeadStatus;
use App\Models\GscPageDaily;
use App\Models\GscSiteDaily;
use App\Models\Lead;
use App\Models\OutboundClick;
use App\Models\Scopes\SiteScope;
use App\Models\Site;
use App\Support\SearchConsole\SearchConsoleClient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
        $to = CarbonImmutable::parse(SearchConsoleClient::latestDataDay()->toDateString(), self::timezone());

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

        // Tổng property theo ngày (khớp màn hình Search Console), không cộng dồn theo trang.
        $search = $this->inPeriod(GscSiteDaily::withoutGlobalScope(SiteScope::class)->whereIn('site_id', $ids))
            ->groupBy('site_id')
            ->selectRaw('site_id, SUM(impressions) AS impressions, SUM(clicks) AS clicks, SUM(position * impressions) AS position_times_impressions')
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
            positionTimesImpressions: (float) ($search[$site->id]->position_times_impressions ?? 0),
            leads: (int) ($leads[$site->id] ?? 0),
            wonLeads: (int) ($won[$site->id]->total ?? 0),
            revenue: (int) ($won[$site->id]->revenue ?? 0),
        ))->values()->all();
    }

    /** @return Collection<int, GscPageDaily> trang nhiều lượt nhấp nhất (page_url, clicks, impressions) */
    public function topPages(Site $site): Collection
    {
        return $this->inPeriod(GscPageDaily::withoutGlobalScope(SiteScope::class)->where('site_id', $site->id))
            ->groupBy('page_hash', 'page_url')
            ->selectRaw('page_url, SUM(clicks) AS clicks, SUM(impressions) AS impressions')
            ->orderByDesc('clicks')->orderBy('page_url')
            ->limit(self::TOP_PAGES)
            ->get();
    }

    /**
     * Lượt bấm sang web chính theo site trong kỳ (giờ Việt Nam).
     *
     * @param  iterable<Site>  $sites
     * @return array<int, int> site_id => lượt
     */
    public function outboundTotals(iterable $sites): array
    {
        $ids = collect($sites)->pluck('id')->all();

        return $this->outbound()->whereIn('site_id', $ids)->groupBy('site_id')
            ->selectRaw('site_id, COUNT(*) AS total')->pluck('total', 'site_id')->map(fn ($n) => (int) $n)->all();
    }

    /** @return Collection<int, object{name: ?string, clicks: int}> sản phẩm được bấm sang web chính nhiều nhất */
    public function topOutboundProducts(Site $site): Collection
    {
        return $this->outbound()->where('outbound_clicks.site_id', $site->id)
            ->leftJoin('products', 'products.id', '=', 'outbound_clicks.product_id')
            ->groupBy('outbound_clicks.product_id', 'products.name')
            ->selectRaw('products.name AS name, COUNT(*) AS clicks')
            ->orderByDesc('clicks')->orderBy('name')->limit(self::TOP_PAGES)->toBase()->get();
    }

    /** @return Collection<int, object{source_path: ?string, clicks: int}> trang (bài viết, danh mục…) đưa khách sang web chính nhiều nhất */
    public function topOutboundSources(Site $site): Collection
    {
        return $this->outbound()->where('site_id', $site->id)
            ->groupBy('source_path')->selectRaw('source_path, COUNT(*) AS clicks')
            ->orderByDesc('clicks')->orderBy('source_path')->limit(self::TOP_PAGES)->toBase()->get();
    }

    /** @return Builder<OutboundClick> */
    private function outbound(): Builder
    {
        [$start, $end] = $this->utcBounds();

        return OutboundClick::withoutGlobalScope(SiteScope::class)->whereBetween('outbound_clicks.clicked_at', [$start, $end]);
    }

    /**
     * Ngày Search Console là ngày theo lịch (giờ Thái Bình Dương); lọc theo đúng ngày lịch đã chọn.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function inPeriod(Builder $query): Builder
    {
        return $query->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()]);
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
