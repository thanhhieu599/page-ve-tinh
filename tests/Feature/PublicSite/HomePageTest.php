<?php

namespace Tests\Feature\PublicSite;

use App\Enums\PageKind;
use App\Models\Category;
use App\Models\CompanyProfile;
use App\Models\Post;
use App\Models\Product;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        CompanyProfile::stored()->fill(['legal_name' => 'Công ty TNHH TMDV Phát triển Kỹ thuật D & T', 'name' => 'D&T', 'url' => 'https://trangthietbi.vn', 'phone' => '0933 568 088'])->save();
        $this->vpp = Site::factory()->create([
            'domain' => 'vpptrangthietbi.com.vn',
            'name' => 'Văn phòng phẩm D&T',
            'settings' => ['tagline' => 'Văn phòng phẩm cho nhà máy: báo giá theo số lượng, xuất VAT, giao tận KCN.'],
        ]);
    }

    public function test_home_page_presents_the_offer_the_newest_articles_products_and_categories(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['name' => 'Giấy văn phòng', 'slug' => 'giay-van-phong']);
        Category::factory()->for($this->vpp)->create(['name' => 'Danh mục chưa có bài', 'slug' => 'trong']);
        $old = Post::factory()->for($this->vpp)->published()->create(['title' => 'Bài cũ về bút bi', 'published_at' => now()->subDays(10)]);
        $new = Post::factory()->for($this->vpp)->published()->create(['title' => 'Cách chọn giấy in A4', 'published_at' => now()->subDay()]);
        $new->categories()->attach($paper);
        Post::factory()->for($this->vpp)->create(['title' => 'Bài nháp chưa đăng']);
        Product::factory()->for($this->vpp)->create(['name' => 'Giấy Double A A4 80gsm', 'slug' => 'giay-double-a-a4-80gsm']);
        Product::factory()->for($this->vpp)->create(['name' => 'Sản phẩm đã ngừng bán', 'is_active' => false]);

        $this->get('https://vpptrangthietbi.com.vn/')
            ->assertOk()
            ->assertSee('<title>Văn phòng phẩm D&amp;T</title>', escape: false)
            ->assertSee('<link rel="canonical" href="https://vpptrangthietbi.com.vn/">', escape: false)
            ->assertSeeText('Văn phòng phẩm cho nhà máy: báo giá theo số lượng, xuất VAT, giao tận KCN.')
            ->assertSee('href="/yeu-cau-bao-gia"', escape: false)
            ->assertSeeTextInOrder(['Cách chọn giấy in A4', 'Bài cũ về bút bi'])
            ->assertSee('href="/san-pham/giay-double-a-a4-80gsm"', escape: false)
            ->assertSee('href="/danh-muc/giay-van-phong"', escape: false)
            ->assertSee('href="tel:0933568088"', escape: false)
            ->assertDontSeeText('Bài nháp chưa đăng')
            ->assertDontSeeText('Sản phẩm đã ngừng bán')
            ->assertDontSee('href="/danh-muc/trong"', escape: false);
    }

    public function test_the_main_menu_links_categories_and_published_required_pages(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['name' => 'Giấy văn phòng', 'slug' => 'giay-van-phong']);
        Post::factory()->for($this->vpp)->published()->create()->categories()->attach($paper);
        $this->vpp->requiredPages()->where('kind', PageKind::About)->update(['is_published' => true]);

        $html = (string) $this->get('https://vpptrangthietbi.com.vn/kien-thuc/'.Post::query()->firstOrFail()->slug)->getContent();

        $this->assertSame(1, preg_match('#<nav[^>]*aria-label="Menu chính"[^>]*>.*?</nav>#s', $html, $menu));
        $this->assertStringContainsString('href="/"', $menu[0]);
        $this->assertStringContainsString('href="/danh-muc/giay-van-phong"', $menu[0]);
        $this->assertStringContainsString('href="/gioi-thieu"', $menu[0]);
        $this->assertStringNotContainsString('href="/lien-he"', $menu[0]);
    }

    public function test_a_brand_new_site_home_page_still_works_and_invites_a_quote(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/')
            ->assertOk()
            ->assertSee('href="/yeu-cau-bao-gia"', escape: false);
    }

    public function test_the_home_page_is_in_the_sitemap(): void
    {
        $this->assertStringContainsString(
            '<loc>https://vpptrangthietbi.com.vn/</loc>',
            (string) $this->get('https://vpptrangthietbi.com.vn/sitemap.xml')->getContent(),
        );
    }

    public function test_home_page_shows_featured_products_then_one_shelf_per_category_with_a_see_all_link(): void
    {
        $office = Category::factory()->for($this->vpp)->create(['name' => 'Dụng cụ văn phòng', 'slug' => 'dung-cu-van-phong']);
        $staplers = Category::factory()->for($this->vpp)->childOf($office)->create(['name' => 'Bấm kim', 'slug' => 'bam-kim']);
        Category::factory()->for($this->vpp)->create(['name' => 'Máy tính', 'slug' => 'may-tinh']);
        Product::factory()->for($this->vpp)->create(['name' => 'Máy đục lỗ E0130', 'is_featured' => true, 'category_id' => $office->id]);
        Product::factory()->for($this->vpp)->create(['name' => 'Máy bấm kim E0395', 'category_id' => $staplers->id]);

        $html = (string) $this->get('https://vpptrangthietbi.com.vn/')->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<section[^>]*aria-labelledby="noi-bat".*?</section>#s', $html, $featured));
        $this->assertStringContainsString('Máy đục lỗ E0130', $featured[0]);
        $this->assertStringNotContainsString('Máy bấm kim E0395', $featured[0]);

        $this->assertSame(1, preg_match('#<section[^>]*aria-labelledby="danh-muc-dung-cu-van-phong".*?</section>#s', $html, $shelf));
        $this->assertStringContainsString('Dụng cụ văn phòng', $shelf[0]);
        $this->assertStringContainsString('Máy bấm kim E0395', $shelf[0]);
        $this->assertStringContainsString('href="/danh-muc/dung-cu-van-phong">Xem tất cả', $shelf[0]);
        $this->assertStringNotContainsString('aria-labelledby="danh-muc-may-tinh"', $html);
    }

    public function test_without_featured_products_the_newest_products_are_featured(): void
    {
        Product::factory()->for($this->vpp)->create(['name' => 'Sản phẩm mới nhất']);

        $html = (string) $this->get('https://vpptrangthietbi.com.vn/')->getContent();

        $this->assertSame(1, preg_match('#<section[^>]*aria-labelledby="noi-bat".*?</section>#s', $html, $featured));
        $this->assertStringContainsString('Sản phẩm mới nhất', $featured[0]);
    }

    public function test_every_page_header_has_a_product_search_box_and_a_category_menu(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['name' => 'Giấy in văn phòng', 'slug' => 'giay-in']);
        Product::factory()->for($this->vpp)->create(['category_id' => $paper->id]);

        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')
            ->assertSee('action="/tim-kiem"', escape: false)
            ->assertSee('name="q"', escape: false)
            ->assertSeeText('Danh mục sản phẩm')
            ->assertSee('href="/danh-muc/giay-in"', escape: false)
            ->assertSee('href="/kien-thuc"', escape: false);
    }
}
