<?php

namespace App\Jobs;

use App\Models\Site;
use App\Support\SearchConsole\SearchConsoleClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Kéo số liệu Search Console theo trang của một site, 7 ngày gần nhất có dữ liệu
 * (Google trễ khoảng 2 ngày). Ghi đè theo (site, trang, ngày) nên chạy lại không nhân đôi,
 * và số liệu tạm của ngày gần nhất được cập nhật ở lần chạy sau.
 */
class PullSearchConsoleData implements ShouldQueue
{
    use Queueable;

    public const DAYS = 7;

    public const DELAY_DAYS = 2;

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [300, 1800];
    }

    public function __construct(public int $siteId) {}

    public function handle(SearchConsoleClient $client = new SearchConsoleClient): void
    {
        $site = Site::query()->find($this->siteId);
        $property = $site?->searchConsoleProperty();

        if ($property === null || ! SearchConsoleClient::configured()) {
            return;
        }

        $end = Carbon::now((string) config('app.display_timezone'))->startOfDay()->subDays(self::DELAY_DAYS);
        $start = $end->copy()->subDays(self::DAYS - 1);
        $now = now();

        $rows = array_map(fn (array $row): array => [
            'site_id' => $site->id,
            'page_url' => mb_substr($row['page'], 0, 512),
            'date' => $row['date'],
            'impressions' => $row['impressions'],
            'clicks' => $row['clicks'],
            'ctr' => round($row['ctr'], 4),
            'position' => round($row['position'], 2),
            'created_at' => $now,
            'updated_at' => $now,
        ], $client->pageDaily($property, $start->toDateString(), $end->toDateString()));

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('gsc_page_daily')->upsert($chunk, ['site_id', 'page_url', 'date'], ['impressions', 'clicks', 'ctr', 'position', 'updated_at']);
        }
    }
}
