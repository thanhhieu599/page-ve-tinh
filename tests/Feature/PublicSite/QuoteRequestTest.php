<?php

namespace Tests\Feature\PublicSite;

use App\Models\Lead;
use App\Models\Post;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use App\Support\Consent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class QuoteRequestTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    private const FORM = 'https://vpptrangthietbi.com.vn/yeu-cau-bao-gia';

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    /** @return array<string, mixed> */
    private function validInput(array $overrides = []): array
    {
        return [
            'name' => 'Nguyễn Thị Mai',
            'company' => 'Công ty TNHH Điện tử ABC Việt Nam',
            'email' => 'mai.nguyen@abc-vn.com',
            'phone' => '0912 345 678',
            'need' => 'Cần báo giá 200 thùng giấy A4 80gsm giao hàng tháng tại KCN Yên Phong.',
            'consent' => '1',
            'page_url' => '/yeu-cau-bao-gia',
            ...$overrides,
        ];
    }

    private function leadPageAsEditor(): TestResponse
    {
        $editor = User::factory()->editor()->hasAttached($this->vpp)->create();

        return $this->actingAs($editor)->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/leads');
    }

    public function test_quote_request_is_saved_with_source_and_consent_evidence(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 02:00:00', 'UTC'));
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'chon-giay-in']);

        // Khách vào từ quảng cáo, đọc bài, rồi mở form.
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/chon-giay-in?utm_source=google&utm_medium=cpc&utm_campaign=giay-a4-fdi')->assertOk();
        $this->get(self::FORM)->assertOk()->assertSee(Consent::TEXT)->assertSee('name="consent"', escape: false)
            ->assertDontSee('checked', escape: false);

        $this->post(self::FORM, $this->validInput(), ['REMOTE_ADDR' => '203.0.113.7'])
            ->assertRedirect('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui');

        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui')->assertOk()->assertSee('Đã nhận yêu cầu báo giá');

        $this->leadPageAsEditor()
            ->assertSee('Nguyễn Thị Mai')
            ->assertSee('Công ty TNHH Điện tử ABC Việt Nam')
            ->assertSee('mai.nguyen@abc-vn.com')
            ->assertSee('google')->assertSee('cpc')->assertSee('giay-a4-fdi')
            ->assertSee('/kien-thuc/chon-giay-in')
            ->assertSee('Mới');
    }

    public function test_request_without_consent_is_refused_and_not_saved(): void
    {
        $this->get(self::FORM);
        $this->post(self::FORM, $this->validInput(['consent' => null]))
            ->assertRedirect(self::FORM)
            ->assertSessionHasErrors('consent');

        $this->leadPageAsEditor()->assertDontSee('Nguyễn Thị Mai');
    }

    public function test_bot_filling_the_hidden_field_sees_success_but_nothing_is_saved(): void
    {
        $this->post(self::FORM, $this->validInput(['website' => 'http://spam.example']))
            ->assertRedirect('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui');

        $this->leadPageAsEditor()->assertDontSee('Nguyễn Thị Mai');
    }

    public function test_too_many_requests_from_one_ip_are_blocked(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post(self::FORM, $this->validInput(['email' => "k{$i}@abc-vn.com"]), ['REMOTE_ADDR' => '198.51.100.9'])
                ->assertRedirect('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui');
        }

        $this->post(self::FORM, $this->validInput(['email' => 'k6@abc-vn.com']), ['REMOTE_ADDR' => '198.51.100.9'])
            ->assertStatus(429);
        $this->leadPageAsEditor()->assertDontSee('k6@abc-vn.com');
    }

    public function test_lead_belongs_to_the_site_it_was_sent_from(): void
    {
        Site::factory()->create(['domain' => 'ppe.example.vn']);
        $this->post(self::FORM, $this->validInput());

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/leads')->assertSee('Nguyễn Thị Mai');
        $this->actingAs($admin)->get('https://admin.trangthietbi.test/admin/ppe.example.vn/leads')->assertDontSee('Nguyễn Thị Mai');
    }

    public function test_turnstile_passes_marks_the_lead_verified(): void
    {
        config(['sites.turnstile.site_key' => 'site-key', 'sites.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->get(self::FORM)->assertSee('data-sitekey="site-key"', escape: false);
        $this->post(self::FORM, $this->validInput(['cf-turnstile-response' => 'token-tot']))
            ->assertRedirect('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui');

        Http::assertSent(fn ($request) => $request['secret'] === 'secret' && $request['response'] === 'token-tot');
        $this->leadPageAsEditor()->assertSee('Nguyễn Thị Mai');
    }

    public function test_failed_turnstile_check_is_refused(): void
    {
        config(['sites.turnstile.site_key' => 'site-key', 'sites.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->get(self::FORM);
        $this->post(self::FORM, $this->validInput(['cf-turnstile-response' => 'token-gia']))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->leadPageAsEditor()->assertDontSee('Nguyễn Thị Mai');
    }

    public function test_without_javascript_the_request_is_still_accepted_but_marked_unverified(): void
    {
        config(['sites.turnstile.site_key' => 'site-key', 'sites.turnstile.secret_key' => 'secret']);
        Http::fake();

        $this->post(self::FORM, $this->validInput())
            ->assertRedirect('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui');

        Http::assertNothingSent();
        $lead = Lead::query()->firstOrFail();
        $editor = User::factory()->editor()->hasAttached($this->vpp)->create();
        $this->actingAs($editor)
            ->get("https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/leads/{$lead->id}/edit")
            ->assertSee('Chưa xác minh');
    }

    public function test_quote_button_on_a_product_prefills_the_form_and_links_the_lead_to_the_product(): void
    {
        $product = Product::factory()->for($this->vpp)->create(['name' => 'Giấy Double A A4 80gsm', 'sku' => 'DA-A4-80']);

        $this->get(self::FORM.'?san-pham=DA-A4-80')
            ->assertOk()
            ->assertSee('Sản phẩm: Giấy Double A A4 80gsm (DA-A4-80)')
            ->assertSee('name="product_sku" value="DA-A4-80"', escape: false);

        $this->post(self::FORM, $this->validInput(['product_sku' => 'DA-A4-80']));

        $lead = Lead::query()->firstOrFail();
        $editor = User::factory()->editor()->hasAttached($this->vpp)->create();
        $this->actingAs($editor)
            ->get("https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/leads/{$lead->id}/edit")
            ->assertSee('Giấy Double A A4 80gsm')
            ->assertSee(Consent::TEXT);
    }

    public function test_every_post_ends_with_a_quote_form(): void
    {
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'chon-giay-in']);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/chon-giay-in')
            ->assertSee('action="/yeu-cau-bao-gia"', escape: false)
            ->assertSee('name="page_url" value="/kien-thuc/chon-giay-in"', escape: false);
    }

    public function test_thank_you_page_reports_a_ga4_conversion_when_ga4_is_configured(): void
    {
        $this->vpp->update(['settings' => ['ga4_id' => 'G-TEST123']]);

        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui')
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123', escape: false)
            ->assertSee("gtag('event', 'generate_lead'", escape: false);
    }

    public function test_pages_carry_no_ga4_code_when_it_is_not_configured(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui')->assertDontSee('googletagmanager', escape: false);
    }
}
