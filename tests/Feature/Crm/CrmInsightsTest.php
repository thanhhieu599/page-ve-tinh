<?php

namespace Tests\Feature\Crm;

use App\Enums\Crm\OrderStatus;
use App\Enums\Crm\QuoteStatus;
use App\Enums\LeadStatus;
use App\Filament\Crm\Pages\CrmSettings;
use App\Filament\Crm\Pages\MisaExportPage;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Site;
use App\Models\User;
use App\Support\Crm\CrmNotifier;
use App\Support\Crm\MisaExport;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/** CRM: tổng quan, báo cáo theo site/nhân viên, nhật ký thông báo, xuất MISA, cài đặt, sản phẩm/thương hiệu. */
class CrmInsightsTest extends TestCase
{
    use RefreshDatabase;

    private const CRM = 'https://admin.trangthietbi.test/crm';

    private Site $vpp;

    private Site $ppe;

    private User $manager;

    private User $an;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.mautic.url' => null]);
        $this->travelTo(Carbon::parse('2026-11-13 03:00:00', 'UTC'));
        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn', 'name' => 'Văn phòng phẩm D&T']);
        $this->ppe = Site::factory()->create(['domain' => 'ppetrangthietbi.vn', 'name' => 'PPE D&T']);
        $this->manager = User::factory()->salesManager()->create(['name' => 'Trưởng phòng']);
        $this->an = User::factory()->sales()->create(['name' => 'Nguyễn Văn An']);

        $samsung = Customer::query()->create(['name' => 'Samsung Electronics VN', 'tax_code' => '2300325764', 'address' => 'KCN Yên Phong, Bắc Ninh', 'owner_id' => $this->an->id]);
        $samsung->contacts()->create(['name' => 'Chị Mua', 'phone' => '0901234567', 'email' => 'mua@samsung.com', 'is_primary' => true]);
        foreach ([[$this->vpp, LeadStatus::Won], [$this->vpp, LeadStatus::New], [$this->ppe, LeadStatus::Lost]] as [$site, $status]) {
            Lead::withoutEvents(fn () => Lead::withoutGlobalScopes()->forceCreate([
                'site_id' => $site->id, 'name' => 'K', 'company' => 'C', 'email' => 'k@c.vn', 'phone' => '0900000000', 'need' => 'Găng tay',
                'consent_text' => 'Đồng ý', 'consent_at' => now(), 'status' => $status, 'assigned_to' => $this->an->id,
            ]));
        }
        $order = Order::query()->create(['customer_id' => $samsung->id, 'owner_id' => $this->an->id, 'site_id' => $this->ppe->id, 'vat_rate' => 8, 'invoice_number' => '0000123']);
        $order->items()->create(['sku' => 'TOWA-370', 'name' => 'Găng tay TOWA 370', 'unit' => 'đôi', 'quantity' => 1000, 'unit_price' => 25000]);
        $order->recalculateTotals();
        Order::query()->create(['customer_id' => $samsung->id, 'owner_id' => $this->an->id, 'status' => OrderStatus::Cancelled])->items()->create(['name' => 'Huỷ', 'quantity' => 1, 'unit_price' => 999]);
        $lost = Quote::query()->create(['customer_id' => $samsung->id, 'owner_id' => $this->an->id]);
        $lost->items()->create(['name' => 'Kính BHLĐ', 'quantity' => 10, 'unit_price' => 50000]);
        $lost->recalculateTotals();
        $lost->markLost('Giá cao hơn đối thủ');
        Quote::query()->create(['customer_id' => $samsung->id, 'owner_id' => $this->an->id, 'status' => QuoteStatus::Sent]);
    }

    public function test_the_overview_shows_this_months_figures(): void
    {
        $this->actingAs($this->manager)->get(self::CRM)->assertOk()
            ->assertSeeText('Doanh số tháng này')->assertSeeText('27.000.000 đ')
            ->assertSeeText('Công nợ quá hạn')->assertSeeText('Lead mới chưa giao');
    }

    public function test_the_report_breaks_sales_down_by_site_and_by_salesperson(): void
    {
        $this->actingAs($this->manager)->get(self::CRM.'/bao-cao?from=2026-11-01&to=2026-11-30')->assertOk()
            ->assertSeeTextInOrder(['Theo site', 'PPE D&T', '27.000.000 đ', 'Văn phòng phẩm D&T', '2', '1', '50,0%'])
            ->assertSeeTextInOrder(['Theo nhân viên', 'Nguyễn Văn An', '3', '2', '0', '27.000.000 đ'])
            ->assertSeeTextInOrder(['Lý do thua', 'Giá cao hơn đối thủ', '1', '550.000 đ']);
    }

    public function test_a_salesperson_reports_only_on_their_own_work(): void
    {
        $this->actingAs(User::factory()->sales()->create(['name' => 'Người mới']))->get(self::CRM.'/bao-cao?from=2026-11-01&to=2026-11-30')
            ->assertOk()->assertDontSeeText('Nguyễn Văn An')->assertDontSeeText('27.000.000');
    }

    public function test_sales_lines_and_customers_export_to_excel_for_misa(): void
    {
        $rows = $this->read(MisaExport::sales(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30')));
        $this->assertSame(MisaExport::SALES_HEADER, $rows[0]);
        $this->assertCount(2, $rows, 'Đơn huỷ không xuất.');
        $code = MisaExport::customerCode(Customer::query()->sole());
        $this->assertSame(['13/11/2026', 'DH-2026-0001', '0000123', $code, 'Samsung Electronics VN', '2300325764'], array_slice($rows[1], 0, 6));
        $this->assertSame(['TOWA-370', 'Găng tay TOWA 370', 'đôi', 1000, 25000, 25000000, 8, 2000000], array_slice($rows[1], 8, 8));

        $customers = $this->read(MisaExport::customers());
        $this->assertSame([$code, 'Samsung Electronics VN', '2300325764', 'KCN Yên Phong, Bắc Ninh', '', '0901234567', 'mua@samsung.com', 'Chị Mua'], $customers[1]);

        Filament::setCurrentPanel(Filament::getPanel('crm'));
        $this->actingAs($this->manager);
        Livewire::test(MisaExportPage::class)->callAction('sales', data: ['from' => '2026-11-01', 'to' => '2026-11-30'])->assertFileDownloaded('misa-ban-hang-20261101-20261130.xlsx');
        $this->actingAs($this->an)->get(self::CRM.'/xuat-misa')->assertForbidden();
    }

    public function test_quote_defaults_are_set_in_crm_settings(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('crm'));
        $this->actingAs($this->manager);
        Livewire::test(CrmSettings::class)
            ->fillForm(['quote_vat_rate' => 8, 'quote_valid_days' => 30, 'quote_payment_terms' => 'Chuyển khoản 45 ngày', 'default_reorder_cycle_days' => 30])
            ->call('save')->assertHasNoFormErrors();

        $quote = Quote::query()->create(['customer_id' => Customer::query()->value('id'), 'owner_id' => $this->manager->id]);
        $this->assertSame([8.0, 'Chuyển khoản 45 ngày', '2026-12-13'], [(float) $quote->vat_rate, $quote->payment_terms, $quote->valid_until->toDateString()]);
        $this->actingAs($this->an)->get(self::CRM.'/cai-dat')->assertForbidden();
    }

    public function test_the_notification_log_lists_who_was_told_what(): void
    {
        CrmNotifier::leadAssigned(Lead::withoutGlobalScopes()->first(), $this->an);

        $this->actingAs($this->manager)->get(self::CRM.'/nhat-ky-thong-bao')->assertOk()
            ->assertSeeText('Bạn được giao khách hàng tiềm năng')->assertSeeText('Nguyễn Văn An');
        $this->actingAs(User::factory()->sales()->create())->get(self::CRM.'/nhat-ky-thong-bao')->assertOk()
            ->assertDontSeeText('Bạn được giao khách hàng tiềm năng');
    }

    public function test_products_of_every_site_and_brands_are_listed(): void
    {
        Product::factory()->for($this->vpp)->create(['name' => 'Giấy Double A A4']);
        Product::factory()->for($this->ppe)->create(['name' => 'Găng tay TOWA 370']);

        $this->actingAs($this->an)->get(self::CRM.'/san-pham')->assertOk()
            ->assertSeeText('Giấy Double A A4')->assertSeeText('Găng tay TOWA 370')->assertSeeText('ppetrangthietbi.vn');
        $this->actingAs($this->manager)->get(self::CRM.'/thuong-hieu')->assertOk();
    }

    /** @return list<list<mixed>> */
    private function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();
        @unlink($path);

        return $rows;
    }
}
