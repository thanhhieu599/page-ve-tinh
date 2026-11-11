<?php

namespace Tests\Feature\PublicSite;

use App\Models\Category;
use App\Models\OutboundClick;
use App\Models\Post;
use App\Models\Product;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sản phẩm dẫn về web chính trangthietbi.vn (grilling 11/11/2026): không còn trang chi tiết trên
 * site vệ tinh; mọi chỗ bấm sản phẩm mở trang trên web chính (tab mới, qua /mua/ để đếm lượt, gắn UTM);
 * thiếu link thì mở form báo giá; link /san-pham/ cũ chuyển 301. Giá tham khảo vẫn theo quy tắc 14 ngày.
 */
class MainSiteProductLinksTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'https://vpptrangthietbi.com.vn';

    private Site $vpp;

    private Category $paper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        $this->paper = Category::factory()->for($this->vpp)->create(['name' => 'Giấy văn phòng', 'slug' => 'giay-van-phong']);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->for($this->vpp)->create([
            'slug' => 'giay-double-a-a4-80gsm',
            'name' => 'Giấy Double A A4 80gsm',
            'sku' => 'DA-A4-80',
            'buy_url' => 'https://trangthietbi.vn/giay-double-a-a4-80gsm',
            'category_id' => $this->paper->id,
            ...$attributes,
        ]);
    }

    private function postWith(Product ...$products): Post
    {
        $post = Post::factory()->for($this->vpp)->published()->create(['slug' => 'chon-giay-in']);
        $post->products()->attach(collect($products)->pluck('id'));

        return $post;
    }

    public function test_product_cards_open_the_main_site_in_a_new_tab_through_the_click_counter(): void
    {
        $this->product();

        $html = (string) $this->get(self::HOST.'/danh-muc/giay-van-phong')->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<li class="product-card">.*?</li>#s', $html, $card));
        $this->assertStringContainsString('href="/mua/da-a4-80?vt=the-sp&amp;tu=/danh-muc/giay-van-phong" class="product-card-link" target="_blank" rel="noopener" data-outbound="the-sp"', $card[0]);
        $this->assertStringContainsString('href="/yeu-cau-bao-gia?san-pham=DA-A4-80"', $card[0], 'Nút báo giá vẫn mở form trên site vệ tinh.');
        $this->assertStringNotContainsString('/san-pham/', $html);
    }

    public function test_sidebar_and_post_product_links_also_go_to_the_main_site(): void
    {
        $this->product(['is_featured' => true]);
        $this->postWith(Product::query()->firstOrFail());

        $html = (string) $this->get(self::HOST.'/kien-thuc/chon-giay-in')->assertOk()->getContent();

        $this->assertStringContainsString('href="/mua/da-a4-80?vt=bai-viet&amp;tu=/kien-thuc/chon-giay-in"', $html);
        $this->assertStringContainsString('href="/mua/da-a4-80?vt=cot-phai&amp;tu=/kien-thuc/chon-giay-in"', $html);
        $this->assertStringNotContainsString('href="/san-pham/', $html);
    }

    public function test_a_click_is_counted_then_sent_to_the_main_site_with_tracking_codes(): void
    {
        $product = $this->product(['buy_url' => 'https://trangthietbi.vn/giay-double-a?bien-the=1']);

        $this->get(self::HOST.'/mua/da-a4-80?vt=the-sp&tu=/danh-muc/giay-van-phong')
            ->assertStatus(302)
            ->assertRedirect('https://trangthietbi.vn/giay-double-a?bien-the=1&utm_source=vpptrangthietbi.com.vn&utm_medium=site-ve-tinh&utm_campaign=san-pham&utm_content=the-sp');

        $click = OutboundClick::withoutGlobalScopes()->sole();
        $this->assertSame([$this->vpp->id, $product->id, '/danh-muc/giay-van-phong', 'the-sp'], [$click->site_id, $click->product_id, $click->source_path, $click->placement]);

        // Lần bấm sau vẫn được đếm (không bị cache), bot không được đếm, nguồn lạ bị bỏ.
        $this->get(self::HOST.'/mua/da-a4-80?vt=the-sp&tu=/danh-muc/giay-van-phong')->assertStatus(302);
        $this->get(self::HOST.'/mua/da-a4-80?vt=the-sp', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->assertStatus(302);
        $this->get(self::HOST.'/mua/da-a4-80?vt=hack&tu=//evil.example')->assertStatus(302);

        $this->assertSame(3, OutboundClick::withoutGlobalScopes()->count());
        $this->assertSame(['khac', null], [OutboundClick::withoutGlobalScopes()->latest('id')->first()->placement, OutboundClick::withoutGlobalScopes()->latest('id')->first()->source_path]);
    }

    public function test_a_product_still_missing_its_main_site_link_opens_the_quote_form(): void
    {
        $this->product(['buy_url' => null]);

        $html = (string) $this->get(self::HOST.'/danh-muc/giay-van-phong')->getContent();
        $this->assertStringContainsString('href="/yeu-cau-bao-gia?san-pham=DA-A4-80" class="product-card-link"', $html);
        $this->assertStringNotContainsString('target="_blank"', $html);

        $this->get(self::HOST.'/mua/da-a4-80')->assertRedirect('/yeu-cau-bao-gia?san-pham=DA-A4-80');
    }

    public function test_old_product_pages_move_permanently_to_the_main_site(): void
    {
        $this->product();

        $this->get(self::HOST.'/san-pham/giay-double-a-a4-80gsm')
            ->assertStatus(301)->assertRedirect('https://trangthietbi.vn/giay-double-a-a4-80gsm');
    }

    public function test_old_pages_of_hidden_renamed_or_deleted_products_never_end_in_an_error(): void
    {
        $replacement = Product::factory()->for($this->vpp)->create(['sku' => 'IK-A4', 'buy_url' => 'https://trangthietbi.vn/giay-ik-plus']);
        $this->product(['is_active' => false, 'replacement_product_id' => $replacement->id]);
        $this->get(self::HOST.'/san-pham/giay-double-a-a4-80gsm')->assertStatus(301)->assertRedirect('https://trangthietbi.vn/giay-ik-plus');

        $hidden = Product::factory()->for($this->vpp)->create(['slug' => 'but-cu', 'is_active' => false, 'category_id' => $this->paper->id]);
        $this->get(self::HOST.'/san-pham/but-cu')->assertStatus(301)->assertRedirect(self::HOST.'/danh-muc/giay-van-phong');

        $renamed = Product::factory()->for($this->vpp)->create(['slug' => 'ten-cu', 'buy_url' => 'https://trangthietbi.vn/ten-moi']);
        $renamed->update(['slug' => 'ten-moi']);
        $this->followingRedirects()->get(self::HOST.'/san-pham/ten-cu');
        $this->get(self::HOST.'/san-pham/ten-moi')->assertRedirect('https://trangthietbi.vn/ten-moi');

        $hidden->delete();
        $this->get(self::HOST.'/san-pham/but-cu')->assertStatus(301)->assertRedirect(self::HOST.'/danh-muc/giay-van-phong');

        Product::factory()->for(Site::factory()->create(['domain' => 'ppe.example.vn']))->create(['slug' => 'gang-tay-towa']);
        $this->get(self::HOST.'/san-pham/gang-tay-towa')->assertNotFound();
    }

    public function test_the_click_event_is_sent_to_ga4_when_it_is_set_up(): void
    {
        $this->product();
        $this->vpp->update(['settings' => ['ga4_id' => 'G-TEST123']]);

        $this->get(self::HOST.'/danh-muc/giay-van-phong')
            ->assertSee("gtag('event', 'click_web_chinh'", escape: false);
    }

    public function test_reference_price_follows_the_14_day_rule_and_stock_is_never_shown(): void
    {
        // 2026-11-01 20:00 UTC là 2026-11-02 03:00 giờ Việt Nam.
        $this->travelTo(Carbon::parse('2026-11-01 20:00:00', 'UTC'));
        $product = $this->product();
        $product->setPrice(89000);
        $this->postWith($product);

        $this->get(self::HOST.'/kien-thuc/chon-giay-in')
            ->assertSeeInOrder(['Sản phẩm liên quan', 'Giấy Double A A4 80gsm', '89.000 đ'])
            ->assertSee('Giá tham khảo, chưa gồm VAT. Cập nhật ngày 02/11/2026.')
            ->assertDontSee('Tồn kho')->assertDontSee('Còn hàng');
        $this->get(self::HOST.'/danh-muc/giay-van-phong')->assertSee('89.000 đ');

        $this->travelTo(Carbon::parse('2026-11-16 16:59:00', 'UTC')); // 23:59 ngày 16/11 VN = ngày 14
        $this->get(self::HOST.'/kien-thuc/chon-giay-in')->assertSee('89.000 đ');

        $this->travelTo(Carbon::parse('2026-11-16 17:01:00', 'UTC')); // ngày 15
        $this->get(self::HOST.'/kien-thuc/chon-giay-in')->assertSee('Liên hệ báo giá')->assertDontSee('89.000');
        $this->get(self::HOST.'/danh-muc/giay-van-phong')->assertSee('Liên hệ báo giá')->assertDontSee('89.000');
    }

    public function test_zero_price_means_no_price(): void
    {
        $this->product()->setPrice(0);

        $this->get(self::HOST.'/danh-muc/giay-van-phong')->assertSee('Liên hệ báo giá')->assertDontSee('0 đ');
    }

    public function test_hidden_products_are_not_listed_under_a_post(): void
    {
        $this->postWith($this->product(), Product::factory()->for($this->vpp)->create(['name' => 'Sản phẩm đã ngừng', 'is_active' => false]));

        $this->get(self::HOST.'/kien-thuc/chon-giay-in')->assertSee('Giấy Double A A4 80gsm')->assertDontSee('Sản phẩm đã ngừng');
    }
}
