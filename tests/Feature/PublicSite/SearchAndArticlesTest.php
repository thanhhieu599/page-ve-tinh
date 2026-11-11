<?php

namespace Tests\Feature\PublicSite;

use App\Models\Post;
use App\Models\Product;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAndArticlesTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    public function test_search_finds_live_products_by_name_or_code_and_published_articles_of_this_site(): void
    {
        Product::factory()->for($this->vpp)->create(['name' => 'Máy bấm kim Deli E0395', 'sku' => 'E0395']);
        Product::factory()->for($this->vpp)->create(['name' => 'Kim bấm số 10', 'sku' => 'KB-10']);
        Product::factory()->for($this->vpp)->create(['name' => 'Máy bấm kim ngừng bán', 'is_active' => false]);
        Post::factory()->for($this->vpp)->published()->create(['title' => 'Chọn máy bấm kim cho văn phòng']);
        Post::factory()->for($this->vpp)->create(['title' => 'Bài nháp về máy bấm kim']);
        Post::factory()->for(Site::factory()->create(['domain' => 'ppetrangthietbi.vn']))->published()->create(['title' => 'Máy bấm kim site khác']);

        $this->get('https://vpptrangthietbi.com.vn/tim-kiem?q=m%C3%A1y+b%E1%BA%A5m+kim')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false)
            ->assertSeeText('Máy bấm kim Deli E0395')
            ->assertSeeText('Chọn máy bấm kim cho văn phòng')
            ->assertDontSeeText('Kim bấm số 10')
            ->assertDontSeeText('ngừng bán')
            ->assertDontSeeText('Bài nháp')
            ->assertDontSeeText('site khác');

        $this->get('https://vpptrangthietbi.com.vn/tim-kiem?q=KB-10')->assertSeeText('Kim bấm số 10');
    }

    public function test_search_is_noindex_even_on_an_indexed_site_and_handles_empty_or_odd_queries(): void
    {
        $this->vpp->forceFill(['is_indexable' => true])->save();

        $this->get('https://vpptrangthietbi.com.vn/tim-kiem')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', escape: false)
            ->assertSeeText('Nhập tên sản phẩm, mã hoặc chủ đề cần tìm');

        $this->get('https://vpptrangthietbi.com.vn/tim-kiem?q=%25_%3Cscript%3E')
            ->assertOk()
            ->assertDontSee('<script>', escape: false)
            ->assertSeeText('Không tìm thấy kết quả');
    }

    public function test_the_articles_page_lists_published_posts_newest_first(): void
    {
        Post::factory()->for($this->vpp)->published()->create(['title' => 'Bài cũ', 'published_at' => now()->subDays(5)]);
        Post::factory()->for($this->vpp)->published()->create(['title' => 'Bài mới', 'published_at' => now()->subDay()]);
        Post::factory()->for($this->vpp)->create(['title' => 'Bài nháp']);

        $this->get('https://vpptrangthietbi.com.vn/tin-tuc')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://vpptrangthietbi.com.vn/tin-tuc">', escape: false)
            ->assertSeeTextInOrder(['Bài mới', 'Bài cũ'])
            ->assertDontSeeText('Bài nháp');
    }
}
