<?php

namespace App\Jobs;

use App\Models\GscPageDaily;
use App\Models\GscSiteDaily;
use App\Models\Site;
use App\Support\SearchConsole\SearchConsoleClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kéo số liệu Search Console của một site: tổng property theo ngày và số liệu theo trang,
 * $days ngày gần nhất có số liệu. Ghi đè theo (site, ngày) và (site, trang, ngày) nên chạy lại
 * không nhân đôi, và số liệu tạm của ngày gần nhất được cập nhật thành số chốt ở lần sau.
 */
class PullSearchConsoleData implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const DAILY_DAYS = 7;

    /** Search Console giữ khoảng 16 tháng số liệu. */
    public const MAX_DAYS = 480;

    public int $tries = 3;

    /** Phải nhỏ hơn retry_after của hàng đợi (90 giây). */
    public int $timeout = 85;

    public int $uniqueFor = 3600;

    public function __construct(public int $siteId, public int $days = self::DAILY_DAYS) {}

    public function uniqueId(): string
    {
        return (string) $this->siteId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [300, 1800];
    }

    public function handle(SearchConsoleClient $client): void
    {
        $site = Site::query()->find($this->siteId);
        $property = $site?->searchConsoleProperty();

        if ($property === null) {
            return;
        }

        if (! SearchConsoleClient::configured()) {
            Log::warning('Chưa có file khoá service account Search Console (GSC_CREDENTIALS_PATH), bỏ qua.', ['site' => $site->domain]);

            return;
        }

        $end = SearchConsoleClient::latestDataDay();
        $start = $end->subDays(max(1, min($this->days, self::MAX_DAYS)) - 1);
        $now = now();
        $stamp = ['site_id' => $site->id, 'created_at' => $now, 'updated_at' => $now];
        $metrics = fn (array $row): array => [
            'impressions' => $row['impressions'],
            'clicks' => $row['clicks'],
            'ctr' => round($row['ctr'], 4),
            'position' => round($row['position'], 2),
        ];

        $totals = array_map(fn (array $row): array => [...$stamp, 'date' => $row['date'], ...$metrics($row)], $client->siteDaily($property, $start, $end));
        GscSiteDaily::query()->upsert($totals, ['site_id', 'date'], ['impressions', 'clicks', 'ctr', 'position', 'updated_at']);

        $pages = array_map(fn (array $row): array => [
            ...$stamp,
            'page_url' => mb_substr($row['page'], 0, 2048),
            'page_hash' => sha1($row['page']),
            'date' => $row['date'],
            ...$metrics($row),
        ], $client->pageDaily($property, $start, $end));

        foreach (array_chunk($pages, 1000) as $chunk) {
            GscPageDaily::query()->upsert($chunk, ['site_id', 'page_hash', 'date'], ['impressions', 'clicks', 'ctr', 'position', 'updated_at']);
        }
    }

    /** Hết số lần thử (vd. service account chưa được thêm vào property: lỗi 403). */
    public function failed(?Throwable $exception): void
    {
        Log::error('Không kéo được số liệu Search Console sau mọi lần thử.', ['site_id' => $this->siteId, 'exception' => $exception]);
    }
}
