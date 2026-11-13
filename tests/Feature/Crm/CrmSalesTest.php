<?php

namespace Tests\Feature\Crm;

use App\Enums\Crm\OrderStatus;
use App\Enums\Crm\PaymentStatus;
use App\Enums\Crm\QuoteStatus;
use App\Enums\Crm\TaskType;
use App\Enums\LeadStatus;
use App\Filament\Crm\Resources\Leads\LeadResource;
use App\Filament\Crm\Resources\Leads\Pages\ListLeads;
use App\Filament\Crm\Resources\Orders\Pages\EditOrder;
use App\Filament\Crm\Resources\Quotes\Pages\CreateQuote;
use App\Filament\Crm\Resources\Quotes\Pages\EditQuote;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
use App\Support\Crm\ReorderReminders;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * CRM chung mọi site (13/11/2026): lead từ mọi site về một chỗ, gộp khách trùng, phân lead,
 * nhân viên chỉ thấy phần của mình, báo giá (VAT, in) → thắng tạo đơn, công nợ, nhắc tái đặt hàng.
 */
class CrmSalesTest extends TestCase
{
    use RefreshDatabase;

    private const CRM = 'https://admin.trangthietbi.test/crm';

    private Site $vpp;

    private Site $ppe;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.mautic.url' => null]);
        CompanyProfile::stored()->fill(['legal_name' => 'Công ty TNHH TMDV Phát triển Kỹ thuật D & T', 'name' => 'D&T', 'tax_id' => '0312345678'])->save();
        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        $this->ppe = Site::factory()->create(['domain' => 'ppetrangthietbi.vn']);
    }

    private function crm(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('crm'));
    }

    private function formLead(Site $site, array $input = []): Lead
    {
        $this->post("https://{$site->domain}/yeu-cau-bao-gia", [
            'name' => 'Trần Thị Mua', 'company' => 'Samsung Electronics Việt Nam', 'email' => 'mua@samsung.com',
            'phone' => '0901 234 567', 'need' => '500 đôi găng tay chống tĩnh điện', 'consent' => '1', ...$input,
        ])->assertRedirect();

        return Lead::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_only_sales_people_and_admins_enter_the_crm_and_sales_stay_out_of_site_content(): void
    {
        foreach (['admin' => 200, 'salesManager' => 200, 'sales' => 200, 'editor' => 403, 'writer' => 403] as $role => $status) {
            $this->actingAs(User::factory()->{$role}()->create())->get(self::CRM)->assertStatus($status);
        }
        $this->actingAs(User::factory()->sales()->create())->get('https://admin.trangthietbi.test/admin')->assertForbidden();
    }

    public function test_leads_from_every_site_land_in_one_list_with_their_source(): void
    {
        $this->formLead($this->vpp);
        $this->formLead($this->ppe, ['email' => 'ehs@intel.com', 'company' => 'Intel Products Việt Nam']);

        $this->crm(User::factory()->salesManager()->create());
        Livewire::test(ListLeads::class)
            ->assertCanSeeTableRecords(Lead::withoutGlobalScopes()->get())
            ->assertSee('vpptrangthietbi.com.vn')->assertSee('ppetrangthietbi.vn');
    }

    public function test_a_known_company_writing_from_another_site_is_attached_to_the_same_customer(): void
    {
        $owner = User::factory()->sales()->create();
        $customer = Customer::query()->create(['name' => 'Samsung Electronics Việt Nam', 'email_domain' => 'samsung.com', 'owner_id' => $owner->id]);
        $contact = $customer->contacts()->create(['name' => 'Trần Thị Mua', 'phone' => '+84 901 234 567']);

        $byPhone = $this->formLead($this->ppe, ['email' => 'khac@gmail.com']);
        $byDomain = $this->formLead($this->vpp, ['email' => 'qa@samsung.com', 'phone' => '0999999999']);
        $stranger = $this->formLead($this->vpp, ['email' => 'a@gmail.com', 'phone' => '0911111111', 'company' => 'Nhà máy khác']);

        $this->assertSame([$customer->id, $contact->id, $owner->id], [$byPhone->fresh()->customer_id, $byPhone->fresh()->contact_id, $byPhone->fresh()->assigned_to]);
        $this->assertSame([$customer->id, null], [$byDomain->fresh()->customer_id, $byDomain->fresh()->contact_id]);
        $this->assertNull($stranger->fresh()->customer_id, 'Gmail không dùng để nhận diện công ty.');
    }

    public function test_the_manager_assigns_a_lead_the_salesperson_is_notified_and_sees_only_their_own(): void
    {
        $lead = $this->formLead($this->vpp);
        $other = $this->formLead($this->ppe, ['email' => 'x@intel.com']);
        $sales = User::factory()->sales()->create(['name' => 'Nguyễn Bán Hàng']);

        $this->crm(User::factory()->salesManager()->create());
        Livewire::test(ListLeads::class)->callTableAction('assign', $lead, data: ['assigned_to' => $sales->id])->assertHasNoTableActionErrors();

        $this->assertSame($sales->id, $lead->fresh()->assigned_to);
        $this->assertSame(1, $sales->notifications()->count());

        $this->crm($sales);
        Livewire::test(ListLeads::class)->assertCanSeeTableRecords([$lead])->assertCanNotSeeTableRecords([$other])
            ->assertTableActionHidden('assign', $lead);
        // Lead của người khác: như không tồn tại (không lộ thông tin).
        $this->get(LeadResource::getUrl('edit', ['record' => $other], panel: 'crm'))->assertNotFound();
    }

    public function test_a_quote_adds_vat_prints_and_when_won_becomes_an_order(): void
    {
        CompanyProfile::stored()->fill(['quote_vat_rate' => 8, 'quote_valid_days' => 15, 'quote_payment_terms' => 'Chuyển khoản 30 ngày'])->save();
        $this->travelTo(Carbon::parse('2026-11-13 03:00:00', 'UTC'));
        $lead = $this->formLead($this->vpp);
        $sales = User::factory()->sales()->create();
        $lead->forceFill(['assigned_to' => $sales->id])->save();
        $glove = Product::factory()->for($this->ppe)->create(['name' => 'Găng tay TOWA 370', 'sku' => 'TOWA-370', 'unit' => 'đôi']);
        $glove->setPrice(25000);

        $this->crm($sales);
        Livewire::test(ListLeads::class)->callTableAction('quote', $lead);
        $quote = Quote::query()->sole();
        $this->assertSame(['BG-2026-0001', 8.0, 'Chuyển khoản 30 ngày', '2026-11-28'], [$quote->number, (float) $quote->vat_rate, $quote->payment_terms, $quote->valid_until->toDateString()]);
        $this->assertSame(LeadStatus::Quoted, $lead->fresh()->status);

        Livewire::test(EditQuote::class, ['record' => $quote->getRouteKey()])
            ->fillForm(['items' => [['product_id' => $glove->id, 'name' => 'Găng tay TOWA 370', 'sku' => 'TOWA-370', 'unit' => 'đôi', 'quantity' => 500, 'unit_price' => 25000]]])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame([12_500_000, 1_000_000, 13_500_000], [$quote->fresh()->subtotal, $quote->fresh()->vat_amount, $quote->fresh()->total]);

        $this->get(route('crm.quotes.print', $quote))->assertOk()
            ->assertSee('BẢNG BÁO GIÁ')->assertSee('BG-2026-0001')->assertSee('Găng tay TOWA 370')->assertSee('13.500.000')
            ->assertSee('Mười ba triệu năm trăm nghìn đồng')->assertSee('0312345678');

        Livewire::test(EditQuote::class, ['record' => $quote->getRouteKey()])->callAction('won');
        $order = Order::query()->sole();
        $this->assertSame(['DH-2026-0001', 13_500_000, 1, QuoteStatus::Won], [$order->number, $order->total, $order->items()->count(), $quote->fresh()->status]);
        $this->assertSame([LeadStatus::Won, 13_500_000], [$lead->fresh()->status, $lead->fresh()->deal_value]);

        // Nhân viên khác không mở được báo giá này.
        $this->actingAs(User::factory()->sales()->create())->get(route('crm.quotes.print', $quote))->assertForbidden();
    }

    public function test_a_lost_quote_keeps_the_reason(): void
    {
        $this->crm($sales = User::factory()->sales()->create());
        $quote = Quote::query()->create(['customer_id' => Customer::query()->create(['name' => 'A', 'owner_id' => $sales->id])->id, 'owner_id' => $sales->id]);

        Livewire::test(EditQuote::class, ['record' => $quote->getRouteKey()])
            ->callAction('lost', data: ['reason' => 'Đối thủ giá thấp hơn 5%'])->assertHasNoActionErrors();

        $this->assertSame([QuoteStatus::Lost, 'Đối thủ giá thấp hơn 5%'], [$quote->fresh()->status, $quote->fresh()->lost_reason]);
    }

    public function test_quote_from_a_customer_page_is_prefilled(): void
    {
        $this->crm($sales = User::factory()->sales()->create());
        $customer = Customer::query()->create(['name' => 'Jabil Việt Nam', 'owner_id' => $sales->id]);
        $contact = $customer->contacts()->create(['name' => 'Anh Mua', 'is_primary' => true]);

        $this->get(self::CRM.'/bao-gia/create?customer='.$customer->id)->assertOk();
        Livewire::withQueryParams(['customer' => $customer->id])->test(CreateQuote::class)
            ->assertSchemaStateSet(['customer_id' => $customer->id, 'contact_id' => $contact->id]);
    }

    public function test_payments_track_debt_and_a_delivered_order_schedules_the_reorder_reminder(): void
    {
        CompanyProfile::stored()->fill(['default_reorder_cycle_days' => 30, 'payment_due_days' => 30])->save();
        $this->travelTo(Carbon::parse('2026-11-01 03:00:00', 'UTC'));
        $this->crm($manager = User::factory()->salesManager()->create());
        $customer = Customer::query()->create(['name' => 'Onsemi Việt Nam', 'owner_id' => $manager->id]);
        $order = Order::query()->create(['customer_id' => $customer->id, 'owner_id' => $manager->id, 'vat_rate' => 10]);
        $order->items()->create(['name' => 'Khẩu trang 3M', 'unit' => 'hộp', 'quantity' => 10, 'unit_price' => 100000]);
        $order->recalculateTotals();
        $this->assertSame(['2026-12-01', 1_100_000], [$order->due_date->toDateString(), $order->total]);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])->callAction('payment', data: ['amount' => 600000]);
        $this->assertSame([PaymentStatus::Partial, 500_000], [$order->fresh()->payment_status, $order->fresh()->outstanding()]);

        $this->travelTo(Carbon::parse('2026-12-05 03:00:00', 'UTC'));
        $this->assertSame([$order->id], Order::query()->overdue()->pluck('id')->all(), 'Còn nợ quá hạn.');

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])->callAction('complete');
        $this->assertSame([OrderStatus::Completed, '2027-01-04'], [$order->fresh()->status, $customer->fresh()->next_reorder_at->toDateString()]);

        // 7 ngày trước kỳ đặt lại: tạo một việc nhắc (không tạo trùng).
        $this->travelTo(Carbon::parse('2026-12-29 03:00:00', 'UTC'));
        $this->assertSame(1, ReorderReminders::run());
        $this->assertSame(0, ReorderReminders::run());
        $this->assertSame([TaskType::Reorder, $manager->id], [Task::query()->sole()->type, Task::query()->sole()->assigned_to]);
        $this->assertSame('04/01/2027 09:00', Task::query()->sole()->due_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i'), 'Nhắc lúc 9 giờ sáng giờ Việt Nam.');

        // Báo giá lại theo đơn gần nhất.
        $requote = ReorderReminders::requote($customer->fresh());
        $this->assertSame([1, 1_100_000], [$requote->items()->count(), $requote->total]);
    }

    public function test_each_site_is_linked_into_the_crm_menu_for_admins(): void
    {
        $vpp = Site::query()->where('domain', 'vpptrangthietbi.com.vn')->firstOrFail();
        $vpp->update(['name' => 'Văn phòng phẩm D&T']);
        Site::factory()->create(['domain' => 'site-tat.vn', 'name' => 'Site đang tắt', 'is_active' => false]);

        $html = (string) $this->actingAs(User::factory()->admin()->create())->get(self::CRM)->assertOk()->getContent();
        $this->assertStringContainsString('Văn phòng phẩm D&amp;T', $html);
        foreach (['posts', 'products', 'categories', 'cai-dat-site', 'required-pages'] as $area) {
            $this->assertStringContainsString('href="https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/'.$area.'"', $html, "Thiếu link {$area}");
        }
        $this->assertStringContainsString('href="https://vpptrangthietbi.com.vn"', $html, 'Có link xem website.');
        $this->assertStringNotContainsString('Site đang tắt', $html);

        // Gọi nhiều request trong cùng ứng dụng không nhân đôi menu.
        $again = (string) $this->get(self::CRM)->getContent();
        $this->assertSame(1, substr_count($again, 'admin/vpptrangthietbi.com.vn/posts"'));

        // Nhân viên kinh doanh không có quyền quản trị nội dung: không thấy menu site.
        $this->actingAs(User::factory()->sales()->create())->get(self::CRM)->assertOk()
            ->assertDontSee('admin/vpptrangthietbi.com.vn/posts', escape: false);
    }

    public function test_every_crm_screen_opens(): void
    {
        $this->actingAs($admin = User::factory()->admin()->create());
        $customer = Customer::query()->create(['name' => 'Foxconn', 'owner_id' => $admin->id]);
        $quote = Quote::query()->create(['customer_id' => $customer->id, 'owner_id' => $admin->id]);
        $order = Order::query()->create(['customer_id' => $customer->id, 'owner_id' => $admin->id]);
        $lead = $this->formLead($this->vpp);

        foreach (['', '/khach-hang-tiem-nang', '/khach-hang-tiem-nang/create', "/khach-hang-tiem-nang/{$lead->id}/edit",
            '/khach-hang', '/khach-hang/create', "/khach-hang/{$customer->id}/edit", '/bao-gia', '/bao-gia/create', "/bao-gia/{$quote->id}/edit",
            '/don-hang', '/don-hang/create', "/don-hang/{$order->id}/edit", '/viec-can-lam', '/cham-soc'] as $path) {
            $this->get(self::CRM.$path)->assertOk();
        }
    }
}
