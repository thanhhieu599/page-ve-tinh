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
        $this->get(self::FORM)->assertOk()->assertSee(Consent::text())->assertSee('name="consent"', escape: false)
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
            ->assertSee(Consent::text());
    }

    public function test_pages_link_to_the_separate_quote_page_instead_of_carrying_the_form(): void
    {
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'chon-giay-in']);

        foreach (['/', '/kien-thuc/chon-giay-in'] as $path) {
            $this->get('https://vpptrangthietbi.com.vn'.$path)
                ->assertOk()
                ->assertDontSee('class="quote-form"', escape: false)
                ->assertSee('href="/yeu-cau-bao-gia?tu='.$path.'"', escape: false);
        }
    }

    public function test_a_lead_sent_from_the_quote_page_remembers_the_article_it_came_from(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia?tu=/kien-thuc/chon-giay-in')
            ->assertOk()
            ->assertSee('name="page_url" value="/kien-thuc/chon-giay-in"', escape: false);

        foreach (['//evil.example/x', 'https://evil.example', 'kien-thuc', '/a"><script>'] as $bad) {
            $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia?tu='.rawurlencode($bad))
                ->assertOk()
                ->assertSee('name="page_url" value="/yeu-cau-bao-gia"', escape: false);
        }
    }

    public function test_the_quote_page_is_its_own_section_in_the_main_menu(): void
    {
        $html = (string) $this->get('https://vpptrangthietbi.com.vn/tim-kiem?q=giay')->getContent();
        $this->assertSame(1, preg_match('#<nav aria-label="Menu chính".*?</nav>#s', $html, $nav));
        $this->assertStringContainsString('<a href="/yeu-cau-bao-gia">Báo giá</a>', $nav[0]);

        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')
            ->assertSee('<h1>Yêu cầu báo giá theo số lượng</h1>', escape: false)
            ->assertSee('class="quote-form"', escape: false)
            ->assertSeeText('Quy trình báo giá');
    }

    public function test_a_real_submission_reports_one_ga4_conversion(): void
    {
        $this->vpp->update(['settings' => ['ga4_id' => 'G-TEST123']]);

        $this->post(self::FORM, $this->validInput());

        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui')
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123', escape: false)
            ->assertSee("gtag('event', 'generate_lead'", escape: false);

        // Tải lại trang cảm ơn không được đếm thêm chuyển đổi.
        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui')
            ->assertDontSee("gtag('event', 'generate_lead'", escape: false);
    }

    public function test_visiting_the_thank_you_page_directly_or_as_a_trapped_bot_reports_no_conversion(): void
    {
        $this->vpp->update(['settings' => ['ga4_id' => 'G-TEST123']]);

        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui')
            ->assertDontSee("gtag('event', 'generate_lead'", escape: false);

        $this->post(self::FORM, $this->validInput(['website' => 'spam']));
        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui')
            ->assertDontSee("gtag('event', 'generate_lead'", escape: false);
    }

    public function test_mistakes_in_the_form_do_not_use_up_the_sending_limit(): void
    {
        foreach (range(1, 6) as $i) {
            $this->post(self::FORM, $this->validInput(['email' => 'sai-email']), ['REMOTE_ADDR' => '198.51.100.20'])
                ->assertSessionHasErrors('email');
        }

        $this->post(self::FORM, $this->validInput(), ['REMOTE_ADDR' => '198.51.100.20'])
            ->assertRedirect('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui');
    }

    public function test_limit_page_explains_in_vietnamese_and_links_back(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post(self::FORM, $this->validInput(['email' => "k{$i}@abc-vn.com"]), ['REMOTE_ADDR' => '198.51.100.30']);
        }

        $this->post(self::FORM, $this->validInput(), ['REMOTE_ADDR' => '198.51.100.30'])
            ->assertStatus(429)
            ->assertSee('Bạn đã gửi nhiều yêu cầu')
            ->assertSee('href="/yeu-cau-bao-gia"', escape: false);
    }

    public function test_strict_mode_requires_turnstile(): void
    {
        config(['sites.turnstile.site_key' => 'site-key', 'sites.turnstile.secret_key' => 'secret', 'sites.turnstile.required' => true]);
        Http::fake();

        $this->post(self::FORM, $this->validInput())->assertSessionHasErrors('cf-turnstile-response');
        $this->leadPageAsEditor()->assertDontSee('Nguyễn Thị Mai');
    }

    public function test_meaningless_phone_number_is_refused(): void
    {
        $this->post(self::FORM, $this->validInput(['phone' => '(((((((((']))->assertSessionHasErrors('phone');
        $this->post(self::FORM, $this->validInput(['phone' => '+84 912.345.678']))->assertSessionHasNoErrors();
    }

    public function test_hidden_product_is_not_linked_to_the_lead(): void
    {
        Product::factory()->for($this->vpp)->create(['name' => 'Bìa còng ngừng bán', 'sku' => 'BC-OLD', 'is_active' => false]);

        $this->post(self::FORM, $this->validInput(['product_sku' => 'BC-OLD']));

        $lead = Lead::query()->firstOrFail();
        $editor = User::factory()->editor()->hasAttached($this->vpp)->create();
        $this->actingAs($editor)
            ->get("https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/leads/{$lead->id}/edit")
            ->assertDontSee('Bìa còng ngừng bán');
    }

    public function test_quote_form_carries_no_csrf_token_so_cached_pages_keep_working(): void
    {
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'chon-giay-in']);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/chon-giay-in')->assertDontSee('name="_token"', escape: false);
    }

    public function test_pages_carry_no_ga4_code_when_it_is_not_configured(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui')->assertDontSee('googletagmanager', escape: false);
    }
}
