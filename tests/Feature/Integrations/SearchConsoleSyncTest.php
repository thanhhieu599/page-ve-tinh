<?php

namespace Tests\Feature\Integrations;

use App\Jobs\PullSearchConsoleData;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

        $this->travelTo(Carbon::parse('2026-11-10 01:00:00', 'UTC')); // 08:00 giờ Việt Nam
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

    /** @param  list<array{string, string, int, int, float}>  $rows  [ngày, trang, clicks, impressions, position] */
    private function fakeGoogle(array $rows): void
    {
        Http::fake([
            self::TOKEN_URI => Http::response(['access_token' => 'token-gia', 'expires_in' => 3599, 'token_type' => 'Bearer']),
            self::API.'*' => Http::response(['rows' => array_map(fn (array $row) => [
                'keys' => [$row[0], $row[1]],
                'clicks' => $row[2],
                'impressions' => $row[3],
                'ctr' => $row[3] > 0 ? $row[2] / $row[3] : 0,
                'position' => $row[4],
            ], $rows)]),
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

        (new PullSearchConsoleData($this->vpp->id))->handle();

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
            && $r['startDate'] === '2026-11-02' && $r['endDate'] === '2026-11-08');

        $this->assertSame([
            ['site_id' => $this->vpp->id, 'page_url' => 'https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4', 'date' => '2026-11-02', 'impressions' => 340, 'clicks' => 12, 'position' => 8.4],
            ['site_id' => $this->vpp->id, 'page_url' => 'https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4', 'date' => '2026-11-03', 'impressions' => 310, 'clicks' => 9, 'position' => 7.9],
        ], $this->stored());
    }

    public function test_running_again_updates_the_numbers_instead_of_duplicating_them(): void
    {
        $row = fn (int $clicks, int $impressions, float $position) => ['rows' => [
            ['keys' => ['2026-11-08', 'https://vpptrangthietbi.com.vn/kien-thuc/a'], 'clicks' => $clicks, 'impressions' => $impressions, 'ctr' => $clicks / $impressions, 'position' => $position],
        ]];
        Http::fake([
            self::TOKEN_URI => Http::response(['access_token' => 'token-gia']),
            // Lần chạy sau Google đã chốt số liệu ngày 08/11.
            self::API.'*' => Http::sequence()->push($row(1, 50, 12.0))->push($row(3, 80, 10.5)),
        ]);

        (new PullSearchConsoleData($this->vpp->id))->handle();
        (new PullSearchConsoleData($this->vpp->id))->handle();

        $this->assertSame([
            ['site_id' => $this->vpp->id, 'page_url' => 'https://vpptrangthietbi.com.vn/kien-thuc/a', 'date' => '2026-11-08', 'impressions' => 80, 'clicks' => 3, 'position' => 10.5],
        ], $this->stored());
    }

    public function test_large_result_sets_are_read_page_by_page(): void
    {
        config(['services.search_console.row_limit' => 2]);
        $row = fn (string $page) => ['keys' => ['2026-11-05', "https://vpptrangthietbi.com.vn/{$page}"], 'clicks' => 1, 'impressions' => 10, 'ctr' => 0.1, 'position' => 5.0];
        Http::fake([
            self::TOKEN_URI => Http::response(['access_token' => 'token-gia']),
            self::API.'*' => Http::sequence()
                ->push(['rows' => [$row('a'), $row('b')]])
                ->push(['rows' => [$row('c'), $row('d')]])
                ->push(['rows' => [$row('e')]]),
        ]);

        (new PullSearchConsoleData($this->vpp->id))->handle();

        $this->assertCount(5, $this->stored());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'searchAnalytics') && $r['startRow'] === 4);
    }

    public function test_sites_without_search_console_are_skipped_and_the_daily_run_queues_the_others(): void
    {
        Site::factory()->create(['domain' => 'chua-cau-hinh.vn']);
        $ppe = Site::factory()->create(['domain' => 'ppetrangthietbi.vn', 'settings' => ['gsc_property' => 'https://ppetrangthietbi.vn/']]);
        Site::factory()->inactive()->create(['domain' => 'da-tat.vn', 'settings' => ['gsc_property' => 'sc-domain:da-tat.vn']]);
        Queue::fake();

        $this->artisan('search-console:pull')->assertSuccessful();

        Queue::assertPushed(PullSearchConsoleData::class, 2);
        Queue::assertPushed(PullSearchConsoleData::class, fn (PullSearchConsoleData $job) => $job->siteId === $this->vpp->id);
        Queue::assertPushed(PullSearchConsoleData::class, fn (PullSearchConsoleData $job) => $job->siteId === $ppe->id);
    }

    public function test_the_pull_runs_every_day_on_schedule(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('search-console:pull');
    }

    public function test_without_a_credentials_file_nothing_is_called(): void
    {
        config(['services.search_console.credentials' => null]);
        Http::fake();

        (new PullSearchConsoleData($this->vpp->id))->handle();

        Http::assertNothingSent();
    }
}
