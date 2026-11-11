<?php

namespace Tests\Feature\PublicSite;

use App\Models\Category;
use App\Models\CompanyProfile;
use App\Models\Product;
use App\Models\Site;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Các mẫu bố cục web bán hàng hiện đại áp dụng cho site văn phòng phẩm B2B. */
class StorefrontPatternsTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        CompanyProfile::stored()->fill(['name' => 'D&T', 'legal_name' => 'Công ty D&T', 'phone' => '0933 568 088'])->save();
        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    public function test_home_shows_category_tiles_with_product_count(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['name' => 'Giấy văn phòng', 'slug' => 'giay-van-phong']);
        $a4 = Category::factory()->for($this->vpp)->create(['name' => 'Giấy A4', 'slug' => 'giay-a4', 'parent_id' => $paper->id]);
        Product::factory()->for($this->vpp)->count(2)->create(['category_id' => $a4->id]);
        Storage::fake('public');
        $file = UploadedFile::fake()->image('a.jpg', 800, 800);
        $media = MediaLibrary::store($file->getRealPath(), 'Giấy', $this->vpp->id);
        Product::factory()->for($this->vpp)->create(['category_id' => $paper->id, 'media_id' => $media->id]);
        Product::factory()->for($this->vpp)->create(['category_id' => $paper->id, 'is_active' => false]);

        $html = (string) $this->get('https://vpptrangthietbi.com.vn/')->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<section[^>]*aria-labelledby="danh-muc-noi-bat".*?</section>#s', $html, $tiles));
        $this->assertStringContainsString('href="/danh-muc/giay-van-phong"', $tiles[0]);
        $this->assertStringContainsString('Giấy văn phòng', $tiles[0]);
        $this->assertStringContainsString('3 sản phẩm', $tiles[0]);
        $this->assertStringContainsString('src="'.$media->url(480).'"', $tiles[0], 'Ảnh ô danh mục dùng cỡ ảnh có thật.');
    }

    public function test_each_product_card_has_its_own_quote_button_and_featured_badge(): void
    {
        Product::factory()->for($this->vpp)->create(['sku' => 'DA-A4-80', 'is_featured' => true]);

        $html = (string) $this->get('https://vpptrangthietbi.com.vn/')->getContent();

        $this->assertStringContainsString('href="/yeu-cau-bao-gia?san-pham=DA-A4-80"', $html);
        $this->assertStringContainsString('class="product-badge">Nổi bật<', $html);
    }

    public function test_home_has_a_b2b_supply_banner_between_shelves(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/')
            ->assertSee('aria-labelledby="cung-ung-dinh-ky"', escape: false)
            ->assertSeeText('Cung ứng định kỳ cho nhà máy');
    }

    public function test_every_page_has_floating_call_and_zalo_buttons_when_set(): void
    {
        $html = (string) $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')->getContent();
        $this->assertSame(1, preg_match('#<div class="floating-contact".*?</div>#s', $html, $float));
        $this->assertStringContainsString('href="tel:0933568088"', $float[0]);
        $this->assertStringNotContainsString('zalo', $float[0], 'Chưa nhập Zalo thì không hiện nút Zalo.');

        CompanyProfile::stored()->fill(['socials' => ['zalo' => 'https://zalo.me/0933568088']])->save();
        $html = (string) $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')->getContent();
        preg_match('#<div class="floating-contact".*?</div>#s', $html, $float);
        $this->assertStringContainsString('href="https://zalo.me/0933568088"', $float[0]);
    }
}
