<?php

namespace Tests\Feature\PublicSite;

use App\Models\Post;
use App\Models\Product;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->for($this->vpp)->create([
            'slug' => 'giay-double-a-a4-80gsm',
            'name' => 'Giấy Double A A4 80gsm',
            'sku' => 'DA-A4-80',
            'specs' => ['Định lượng' => '80gsm', 'Quy cách' => '500 tờ/ram'],
            'buy_url' => 'https://trangthietbi.vn/giay-double-a-a4-80gsm',
            ...$attributes,
        ]);
    }

    public function test_fresh_price_is_shown_with_reference_label_and_vietnam_date(): void
    {
        // 2026-11-01 20:00 UTC là 2026-11-02 03:00 giờ Việt Nam.
        $this->travelTo(Carbon::parse('2026-11-01 20:00:00', 'UTC'));
        $this->product()->setPrice(89000);

        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')
            ->assertOk()
            ->assertSee('Giấy Double A A4 80gsm')
            ->assertSee('DA-A4-80')
            ->assertSee('Định lượng')->assertSee('80gsm')
            ->assertSee('89.000 đ')
            ->assertSee('Giá tham khảo, chưa gồm VAT. Cập nhật ngày 02/11/2026.')
            ->assertSee('Yêu cầu báo giá theo số lượng')
            ->assertSee('href="https://trangthietbi.vn/giay-double-a-a4-80gsm"', escape: false)
            ->assertSee('Xem trên trangthietbi.vn')
            ->assertDontSee('Liên hệ báo giá');
    }

    public function test_price_is_still_shown_on_day_14_and_replaced_on_day_15(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 08:00:00', 'UTC'));
        $this->product()->setPrice(89000);

        $this->travelTo(Carbon::parse('2026-11-15 08:00:00', 'UTC')); // đúng 14 ngày
        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')
            ->assertSee('89.000 đ')->assertDontSee('Liên hệ báo giá');

        $this->travelTo(Carbon::parse('2026-11-16 08:00:00', 'UTC')); // ngày 15
        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')
            ->assertSee('Liên hệ báo giá')
            ->assertDontSee('89.000')
            ->assertDontSee('Giá tham khảo');
    }

    public function test_product_without_price_asks_for_a_quote(): void
    {
        $this->product();

        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')
            ->assertSee('Liên hệ báo giá')
            ->assertSee('Yêu cầu báo giá theo số lượng');
    }

    public function test_stock_is_never_shown(): void
    {
        $this->product()->setPrice(89000);

        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')
            ->assertDontSee('Tồn kho')->assertDontSee('Còn hàng')->assertDontSee('Hết hàng');
    }

    public function test_inactive_product_and_product_of_another_site_are_not_found(): void
    {
        $this->product(['is_active' => false]);
        $ppe = Site::factory()->create(['domain' => 'ppe.example.vn']);
        Product::factory()->for($ppe)->create(['slug' => 'gang-tay-towa']);

        // Sản phẩm bị ẩn: trang đã gỡ (410), để Google bỏ khỏi kết quả.
        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')->assertStatus(410);
        $this->get('https://vpptrangthietbi.com.vn/san-pham/gang-tay-towa')->assertNotFound();
    }

    public function test_post_shows_its_related_active_products_with_the_price_rule(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 08:00:00', 'UTC'));
        $fresh = $this->product();
        $fresh->setPrice(89000);
        $old = Product::factory()->for($this->vpp)->create(['name' => 'Bìa còng 7cm', 'slug' => 'bia-cong-7cm']);
        $hidden = Product::factory()->for($this->vpp)->create(['name' => 'Sản phẩm đã ngừng', 'is_active' => false]);
        $post = Post::factory()->for($this->vpp)->published()->create(['slug' => 'chon-giay-in']);
        $post->products()->attach([$fresh->id, $old->id, $hidden->id]);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/chon-giay-in')
            ->assertSeeInOrder(['Sản phẩm liên quan', 'Giấy Double A A4 80gsm', '89.000 đ'])
            ->assertSee('href="/san-pham/giay-double-a-a4-80gsm"', escape: false)
            ->assertSee('Bìa còng 7cm')
            ->assertSee('Liên hệ báo giá')
            ->assertDontSee('Sản phẩm đã ngừng');
    }

    public function test_price_lasts_until_the_end_of_day_14_in_vietnam_time(): void
    {
        // Cập nhật 23:30 ngày 01/11 giờ Việt Nam.
        $this->travelTo(Carbon::parse('2026-11-01 16:30:00', 'UTC'));
        $this->product()->setPrice(89000);

        $this->travelTo(Carbon::parse('2026-11-15 16:59:00', 'UTC')); // 23:59 ngày 15/11 VN = ngày 14
        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')->assertSee('89.000 đ');

        $this->travelTo(Carbon::parse('2026-11-15 17:01:00', 'UTC')); // 00:01 ngày 16/11 VN = ngày 15
        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')
            ->assertSee('Liên hệ báo giá')->assertDontSee('89.000');
    }

    public function test_morning_update_is_not_cut_off_in_the_middle_of_day_14(): void
    {
        // Cập nhật 15:00 ngày 01/11 VN; đến 20:00 ngày 15/11 VN (ngày 14) giá vẫn còn.
        $this->travelTo(Carbon::parse('2026-11-01 08:00:00', 'UTC'));
        $this->product()->setPrice(89000);

        $this->travelTo(Carbon::parse('2026-11-15 13:00:00', 'UTC'));
        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')->assertSee('89.000 đ');
    }

    public function test_zero_price_means_no_price(): void
    {
        $this->product()->setPrice(0);

        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')
            ->assertSee('Liên hệ báo giá')->assertDontSee('0 đ');
    }

    public function test_renaming_a_product_redirects_its_old_url(): void
    {
        $product = $this->product();
        $product->update(['slug' => 'giay-a4-double-a-80gsm']);

        $this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')
            ->assertStatus(301)->assertRedirect('https://vpptrangthietbi.com.vn/san-pham/giay-a4-double-a-80gsm');
    }
}
