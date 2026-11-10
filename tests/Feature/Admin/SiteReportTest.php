<?php

namespace Tests\Feature\Admin;

use App\Enums\LeadStatus;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SiteReportTest extends TestCase
{
    use RefreshDatabase;

    private const REPORT = 'https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/bao-cao';

    private Site $vpp;

    private Site $ppe;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.mautic.url' => null]);
        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn', 'name' => 'Văn phòng phẩm D&T']);
        $this->ppe = Site::factory()->create(['domain' => 'ppetrangthietbi.vn', 'name' => 'PPE D&T']);

        // Tổng property (khớp màn hình Search Console), khác tổng cộng dồn theo trang.
        $this->siteTotal($this->vpp, '2026-11-02', 100, 10, 5.0);
        $this->siteTotal($this->vpp, '2026-11-05', 300, 6, 15.0);
        $this->siteTotal($this->vpp, '2026-10-20', 999, 99, 1.0); // ngoài khoảng
        $this->siteTotal($this->ppe, '2026-11-03', 200, 20, 3.0);
        $this->gsc($this->vpp, 'https://vpptrangthietbi.com.vn/kien-thuc/giay-a4', '2026-11-02', 100, 10, 5.0);
        $this->gsc($this->vpp, 'https://vpptrangthietbi.com.vn/kien-thuc/giay-a4-trung-lap', '2026-11-02', 70, 1, 9.0);
        $this->gsc($this->vpp, 'https://vpptrangthietbi.com.vn/kien-thuc/muc-in', '2026-11-05', 300, 6, 15.0);
        $this->gsc($this->vpp, 'https://vpptrangthietbi.com.vn/kien-thuc/giay-a4', '2026-10-20', 999, 99, 1.0); // ngoài khoảng
        $this->gsc($this->ppe, 'https://ppetrangthietbi.vn/kien-thuc/gang-tay', '2026-11-03', 200, 20, 3.0);

        // Giờ Việt Nam: 01/11 00:30 là 31/10 17:30 UTC (vẫn trong khoảng); 08/11 08:00 VN là ngoài khoảng.
        $this->lead($this->vpp, '2026-10-31 17:30:00');
        $this->lead($this->vpp, '2026-11-04 03:00:00', LeadStatus::Won, 50_000_000, '2026-11-06 02:00:00');
        $this->lead($this->vpp, '2026-11-06 09:00:00');
        $this->lead($this->vpp, '2026-11-08 01:00:00');
        $this->lead($this->vpp, '2026-10-01 03:00:00', LeadStatus::Won, 80_000_000, '2026-10-02 03:00:00'); // chốt ngoài khoảng
        $this->lead($this->ppe, '2026-11-02 03:00:00');
    }

    private function gsc(Site $site, string $page, string $date, int $impressions, int $clicks, float $position): void
    {
        DB::table('gsc_page_daily')->insert([
            'site_id' => $site->id, 'page_url' => $page, 'page_hash' => sha1($page), 'date' => $date,
            'impressions' => $impressions, 'clicks' => $clicks, 'ctr' => $clicks / $impressions, 'position' => $position,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function siteTotal(Site $site, string $date, int $impressions, int $clicks, float $position): void
    {
        DB::table('gsc_site_daily')->insert([
            'site_id' => $site->id, 'date' => $date, 'impressions' => $impressions, 'clicks' => $clicks,
            'ctr' => $clicks / $impressions, 'position' => $position, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function lead(Site $site, string $createdAtUtc, LeadStatus $status = LeadStatus::New, ?int $dealValue = null, ?string $wonAtUtc = null): void
    {
        DB::table('leads')->insert([
            'site_id' => $site->id, 'name' => 'Khách', 'company' => 'Nhà máy', 'email' => 'k@nm.vn', 'phone' => '0900000000',
            'need' => 'Báo giá', 'consent_text' => 'Đồng ý', 'consent_at' => $createdAtUtc,
            'status' => $status->value, 'deal_value' => $dealValue, 'won_at' => $wonAtUtc,
            'created_at' => Carbon::parse($createdAtUtc, 'UTC'), 'updated_at' => Carbon::parse($createdAtUtc, 'UTC'),
        ]);
    }

    private function report(User $user): TestResponse
    {
        return $this->actingAs($user)->get(self::REPORT.'?from=2026-11-01&to=2026-11-07');
    }

    public function test_admin_sees_search_and_sales_totals_for_every_site_in_the_chosen_period(): void
    {
        $response = $this->report(User::factory()->admin()->create())->assertOk();

        // Lượt hiển thị, lượt nhấp, CTR, vị trí trung bình (theo lượt hiển thị), lead, lead thắng, doanh thu đã chốt.
        $response->assertSeeTextInOrder(['vpptrangthietbi.com.vn', '400', '16', '4,0%', '12,5', '3', '1', '50.000.000 đ']);
        $response->assertSeeTextInOrder(['ppetrangthietbi.vn', '200', '20', '10,0%', '3,0', '1', '0', '0 đ']);
        $response->assertDontSeeText('80.000.000');
        $response->assertDontSeeText('999');
    }

    public function test_top_pages_of_each_site_are_ranked_by_clicks(): void
    {
        $this->report(User::factory()->admin()->create())
            // Site xếp theo tên, như bảng tổng hợp.
            ->assertSeeTextInOrder([
                'Top trang: PPE D&T',
                'https://ppetrangthietbi.vn/kien-thuc/gang-tay', '20',
                'Top trang: Văn phòng phẩm D&T',
                'https://vpptrangthietbi.com.vn/kien-thuc/giay-a4', '10',
                'https://vpptrangthietbi.com.vn/kien-thuc/muc-in', '6',
                'https://vpptrangthietbi.com.vn/kien-thuc/giay-a4-trung-lap', '1',
            ]);
    }

    public function test_an_editor_sees_only_the_sites_they_work_on_and_writers_see_nothing(): void
    {
        $editor = User::factory()->editor()->hasAttached($this->vpp)->create();

        $this->report($editor)
            ->assertOk()
            ->assertSeeText('vpptrangthietbi.com.vn')
            ->assertDontSeeText('ppetrangthietbi.vn');

        $this->report(User::factory()->writer()->hasAttached($this->vpp)->create())->assertForbidden();
    }

    public function test_without_dates_the_report_covers_the_last_28_days_with_data(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC')); // 02:00 ngày 09/11 giờ Thái Bình Dương

        $this->actingAs(User::factory()->admin()->create())->get(self::REPORT)
            ->assertOk()
            ->assertSeeText('Từ 11/10/2026 đến 07/11/2026');
    }

    public function test_an_impossible_date_falls_back_to_the_default_period(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC')); // 02:00 ngày 09/11 giờ Thái Bình Dương

        $this->actingAs(User::factory()->admin()->create())->get(self::REPORT.'?from=2026-02-31&to=2026-11-07')
            ->assertOk()
            ->assertSeeText('Từ 11/10/2026 đến 07/11/2026');
    }
}
