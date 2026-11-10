<?php

namespace Tests\Feature\Integrations;

use App\Jobs\PullSearchConsoleData;
use App\Models\Site;
use App\Support\SearchConsole\SearchConsoleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SearchConsoleSyncTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    private const API = 'https://searchconsole.googleapis.com/webmasters/v3/sites/';

    private Site $vpp;

    private string $publicKey;

    private string $credentialsPath;

    /** @var list<array{string, string, int, int, float}> */
    private array $googleRows = [];

    /** @var list<array{string, int, int, float}> */
    private array $googleTotals = [];

    private bool $googleFaked = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Khoá service account giả sinh tại chỗ: kiểm tra được chữ ký JWT như Google kiểm tra.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privatePem);
        $this->publicKey = openssl_pkey_get_details($key)['key'];
        $this->credentialsPath = storage_path('framework/testing/gsc-'.uniqid().'.json');
        @mkdir(dirname($this->credentialsPath), 0777, true);
        file_put_contents($this->credentialsPath, json_encode([
            'type' => 'service_account',
            'client_email' => 'bao-cao@dt-page-ve-tinh.iam.gserviceaccount.com',
            'private_key' => $privatePem,
            'token_uri' => self::TOKEN_URI,
        ]));
        config(['services.search_console.credentials' => $this->credentialsPath]);

        $this->travelTo(Carbon::parse('2026-11-10 01:00:00', 'UTC')); // 08:00 giờ Việt Nam = 17:00 ngày 09/11 giờ Thái Bình Dương
        $this->vpp = Site::factory()->create([
            'domain' => 'vpptrangthietbi.com.vn',
            'settings' => ['gsc_property' => 'sc-domain:vpptrangthietbi.com.vn'],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);

        parent::tearDown();
    }

    /**
     * Google giả: trả số liệu theo trang khi hỏi theo (date, page), tổng property khi hỏi theo date.
     * Gọi lại trong cùng test chỉ đổi dữ liệu trả về (Http::fake lần hai không thay được lần đầu).
     *
     * @param  list<array{string, string, int, int, float}>  $rows  [ngày, trang, clicks, impressions, position]
     * @param  list<array{string, int, int, float}>  $totals  [ngày, clicks, impressions, position]
     */
    private function fakeGoogle(array $rows, array $totals = []): void
    {
        $this->googleRows = $rows;
        $this->googleTotals = $totals;
        if ($this->googleFaked) {
            return;
        }
        $this->googleFaked = true;

        Http::fake([
            self::TOKEN_URI => Http::response(['access_token' => 'token-gia', 'expires_in' => 3599, 'token_type' => 'Bearer']),
            self::API.'*' => function (Request $request) {
                $byPage = $request['dimensions'] === ['date', 'page'];

                return Http::response(['rows' => $byPage
                    ? array_map(fn (array $row) => ['keys' => [$row[0], $row[1]], 'clicks' => $row[2], 'impressions' => $row[3], 'ctr' => $row[3] > 0 ? $row[2] / $row[3] : 0, 'position' => $row[4]], $this->googleRows)
                    : array_map(fn (array $row) => ['keys' => [$row[0]], 'clicks' => $row[1], 'impressions' => $row[2], 'ctr' => $row[2] > 0 ? $row[1] / $row[2] : 0, 'position' => $row[3]], $this->googleTotals),
                ]);
            },
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function stored(): array
    {
        return DB::table('gsc_page_daily')->orderBy('date')->orderBy('page_url')
            ->get(['site_id', 'page_url', 'date', 'impressions', 'clicks', 'position'])
            ->map(fn ($row) => [...(array) $row, 'position' => (float) $row->position])->all();
    }

    public function test_daily_page_numbers_are_pulled_with_a_signed_service_account_login(): void
    {
        $this->fakeGoogle([
            ['2026-11-02', 'https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4', 12, 340, 8.4],
            ['2026-11-03', 'https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4', 9, 310, 7.9],
        ]);

        (new PullSearchConsoleData($this->vpp->id))->handle(app(SearchConsoleClient::class));

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== self::TOKEN_URI) {
                return false;
            }
            $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $request['grant_type']);
            [$header, $claims, $signature] = explode('.', $request['assertion']);
            $decode = fn (string $part) => base64_decode(strtr($part, '-_', '+/'));
            $this->assertSame(1, openssl_verify("{$header}.{$claims}", $decode($signature), $this->publicKey, OPENSSL_ALGO_SHA256));
            $claims = json_decode($decode($claims), true);
            $this->assertSame('bao-cao@dt-page-ve-tinh.iam.gserviceaccount.com', $claims['iss']);
            $this->assertSame('https://www.googleapis.com/auth/webmasters.readonly', $claims['scope']);
            $this->assertSame(self::TOKEN_URI, $claims['aud']);

            return true;
        });
        Http::assertSent(fn (Request $r) => $r->url() === self::API.'sc-domain%3Avpptrangthietbi.com.vn/searchAnalytics/query'
            && $r->header('Authorization')[0] === 'Bearer token-gia'
            && $r['dimensions'] === ['date', 'page']
            && $r['startDate'] === '2026-11-01' && $r['endDate'] === '2026-11-07');

        $this->assertSame([
            ['site_id' => $this->vpp->id, 'page_url' => 'https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4', 'date' => '2026-11-02', 'impressions' => 340, 'clicks' => 12, 'position' => 8.4],
            ['site_id' => $this->vpp->id, 'page_url' => 'https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4', 'date' => '2026-11-03', 'impressions' => 310, 'clicks' => 9, 'position' => 7.9],
        ], $this->stored());
    }

    /** @return list<array<string, mixed>> tổng property theo ngày đã lưu */
    private function storedTotals(): array
    {
        return DB::table('gsc_site_daily')->orderBy('date')->get(['site_id', 'date', 'impressions', 'clicks', 'position'])
            ->map(fn ($row) => [...(array) $row, 'position' => (float) $row->position])->all();
    }

    public function test_site_totals_are_pulled_separately_so_they_match_the_search_console_screen(): void
    {
        // Tổng property nhỏ hơn tổng cộng dồn theo trang (một lượt tìm có thể hiện nhiều trang).
        $this->fakeGoogle(
            [['2026-11-05', 'https://vpptrangthietbi.com.vn/a', 5, 100, 4.0], ['2026-11-05', 'https://vpptrangthietbi.com.vn/b', 3, 90, 6.0]],
            [['2026-11-05', 7, 150, 4.6]],
        );

        (new PullSearchConsoleData($this->vpp->id))->handle(app(SearchConsoleClient::class));

        $this->assertSame([['site_id' => $this->vpp->id, 'date' => '2026-11-05', 'impressions' => 150, 'clicks' => 7, 'position' => 4.6]], $this->storedTotals());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'searchAnalytics') && $r['dimensions'] === ['date'] && $r['dataState'] === 'all');
    }

    public function test_running_again_updates_the_numbers_instead_of_duplicating_them(): void
    {
        $this->fakeGoogle([['2026-11-08', 'https://vpptrangthietbi.com.vn/kien-thuc/a', 1, 50, 12.0]], [['2026-11-08', 1, 50, 12.0]]);
        (new PullSearchConsoleData($this->vpp->id))->handle(app(SearchConsoleClient::class));

        // Lần chạy sau Google đã có số liệu chốt của ngày 08/11.
        $this->fakeGoogle([['2026-11-08', 'https://vpptrangthietbi.com.vn/kien-thuc/a', 3, 80, 10.5]], [['2026-11-08', 3, 80, 10.5]]);
        (new PullSearchConsoleData($this->vpp->id))->handle(app(SearchConsoleClient::class));

        $this->assertSame([
            ['site_id' => $this->vpp->id, 'page_url' => 'https://vpptrangthietbi.com.vn/kien-thuc/a', 'date' => '2026-11-08', 'impressions' => 80, 'clicks' => 3, 'position' => 10.5],
        ], $this->stored());
        $this->assertCount(1, $this->storedTotals());
    }

    public function test_long_page_addresses_that_share_a_prefix_are_kept_apart(): void
    {
        $base = 'https://vpptrangthietbi.com.vn/kien-thuc/a?'.str_repeat('utm_x=1&', 80);
        $this->fakeGoogle([['2026-11-05', $base.'v=1', 1, 10, 3.0], ['2026-11-05', $base.'v=2', 2, 20, 4.0]]);

        (new PullSearchConsoleData($this->vpp->id))->handle(app(SearchConsoleClient::class));

        $this->assertEqualsCanonicalizing([$base.'v=1', $base.'v=2'], array_column($this->stored(), 'page_url'));
    }

    public function test_large_result_sets_are_read_page_by_page(): void
    {
        config(['services.search_console.row_limit' => 2]);
        $pages = array_map(fn (string $p) => ['2026-11-05', "https://vpptrangthietbi.com.vn/{$p}", 1, 10, 5.0], ['a', 'b', 'c', 'd', 'e']);
        Http::fake([
            self::TOKEN_URI => Http::response(['access_token' => 'token-gia']),
            self::API.'*' => function (Request $request) use ($pages) {
                if ($request['dimensions'] !== ['date', 'page']) {
                    return Http::response(['rows' => []]);
                }
                $slice = array_slice($pages, $request['startRow'], $request['rowLimit']);

                return Http::response(['rows' => array_map(fn ($r) => ['keys' => [$r[0], $r[1]], 'clicks' => $r[2], 'impressions' => $r[3], 'ctr' => 0.1, 'position' => $r[4]], $slice)]);
            },
        ]);

        (new PullSearchConsoleData($this->vpp->id))->handle(app(SearchConsoleClient::class));

        $this->assertCount(5, $this->stored());
    }

    public function test_every_configured_site_is_queued_daily_including_switched_off_ones(): void
    {
        Site::factory()->create(['domain' => 'chua-cau-hinh.vn']);
        $ppe = Site::factory()->create(['domain' => 'ppetrangthietbi.vn', 'settings' => ['gsc_property' => 'https://ppetrangthietbi.vn/']]);
        $off = Site::factory()->inactive()->create(['domain' => 'da-tat.vn', 'settings' => ['gsc_property' => 'sc-domain:da-tat.vn']]);
        Queue::fake();

        $this->artisan('search-console:pull')->assertSuccessful();

        Queue::assertPushed(PullSearchConsoleData::class, 3);
        foreach ([$this->vpp, $ppe, $off] as $site) {
            Queue::assertPushed(PullSearchConsoleData::class, fn (PullSearchConsoleData $job) => $job->siteId === $site->id && $job->days === 7);
        }
    }

    public function test_history_can_be_backfilled_once_when_a_site_is_first_connected(): void
    {
        $this->fakeGoogle([]);
        Queue::fake();
        $this->artisan('search-console:pull --days=90')->assertSuccessful();
        Queue::assertPushed(PullSearchConsoleData::class, fn (PullSearchConsoleData $job) => $job->days === 90);

        (new PullSearchConsoleData($this->vpp->id, days: 90))->handle(app(SearchConsoleClient::class));

        // Ngày cuối có số liệu: 07/11 (Search Console tính ngày theo giờ Thái Bình Dương, trễ 2 ngày); 90 ngày tính lùi.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'searchAnalytics') && $r['startDate'] === '2026-08-10' && $r['endDate'] === '2026-11-07');
    }

    public function test_an_expired_login_is_renewed_once_and_the_pull_continues(): void
    {
        $tokens = 0;
        $queries = 0;
        Http::fake([
            self::TOKEN_URI => function () use (&$tokens) {
                $tokens++;

                return Http::response(['access_token' => "token-{$tokens}"]);
            },
            self::API.'*' => function (Request $request) use (&$queries) {
                $queries++;

                return $request->header('Authorization')[0] === 'Bearer token-1'
                    ? Http::response(['error' => 'invalid_token'], 401)
                    : Http::response(['rows' => []]);
            },
        ]);

        (new PullSearchConsoleData($this->vpp->id))->handle(app(SearchConsoleClient::class));

        $this->assertSame(2, $tokens);
    }

    public function test_the_pull_runs_every_day_on_schedule(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('search-console:pull');
    }

    public function test_without_a_credentials_file_nothing_is_called_and_a_warning_is_logged(): void
    {
        config(['services.search_console.credentials' => '/khong-co/file.json']);
        Http::fake();
        Log::spy();

        (new PullSearchConsoleData($this->vpp->id))->handle(app(SearchConsoleClient::class));

        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_pull_that_keeps_failing_is_logged_as_an_error(): void
    {
        Log::spy();

        (new PullSearchConsoleData($this->vpp->id))->failed(new \RuntimeException('403 forbidden'));

        Log::shouldHaveReceived('error')->once();
    }
}
