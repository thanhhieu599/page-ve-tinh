<?php

namespace Tests\Feature\Seo;

use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexingTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'cach-chon-giay-in-a4']);
    }

    public function test_a_new_site_is_noindex_on_every_page(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/khong-co-bai-nay')
            ->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_an_indexed_site_lets_search_engines_index_its_pages(): void
    {
        $this->vpp->forceFill(['is_indexable' => true])->save();

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4')
            ->assertOk()
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertDontSee('name="robots"', escape: false);
    }

    public function test_every_page_declares_its_canonical_url_without_tracking_parameters(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4?utm_source=zalo&gclid=abc')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4">', escape: false);

        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')
            ->assertSee('<link rel="canonical" href="https://vpptrangthietbi.com.vn/yeu-cau-bao-gia">', escape: false);
    }

    public function test_paginated_listings_keep_the_page_number_in_the_canonical_url(): void
    {
        $category = Category::factory()->for($this->vpp)->create(['slug' => 'giay-van-phong']);
        Post::factory()->for($this->vpp)->published()->count(13)->create()
            ->each(fn (Post $post) => $post->categories()->attach($category));

        $this->get('https://vpptrangthietbi.com.vn/danh-muc/giay-van-phong?page=2&utm_source=zalo')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://vpptrangthietbi.com.vn/danh-muc/giay-van-phong?page=2">', escape: false);

        $this->get('https://vpptrangthietbi.com.vn/danh-muc/giay-van-phong?page=1')
            ->assertSee('<link rel="canonical" href="https://vpptrangthietbi.com.vn/danh-muc/giay-van-phong">', escape: false);
    }

    public function test_robots_txt_keeps_crawlers_out_until_the_site_is_indexed(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSeeText("User-agent: *\nDisallow: /\n", escape: false)
            ->assertDontSee('Sitemap:');
    }

    public function test_robots_txt_of_an_indexed_site_allows_crawling_and_points_to_its_own_sitemap(): void
    {
        $this->vpp->forceFill(['is_indexable' => true])->save();
        Site::factory()->create(['domain' => 'ppetrangthietbi.vn']);

        $this->get('https://vpptrangthietbi.com.vn/robots.txt')
            ->assertOk()
            ->assertDontSee("Disallow: /\n", escape: false)
            ->assertSee('Sitemap: https://vpptrangthietbi.com.vn/sitemap.xml', escape: false);

        $this->get('https://ppetrangthietbi.vn/robots.txt')
            ->assertSeeText("Disallow: /\n", escape: false)
            ->assertDontSee('Sitemap:');
    }

    public function test_the_admin_domain_is_never_crawled(): void
    {
        $this->get('https://admin.trangthietbi.test/robots.txt')
            ->assertOk()
            ->assertSeeText("Disallow: /\n", escape: false);
    }

    public function test_the_quote_thank_you_page_stays_out_of_the_index_on_an_indexed_site(): void
    {
        $this->vpp->forceFill(['is_indexable' => true])->save();

        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia/da-gui')
            ->assertSee('<meta name="robots" content="noindex">', escape: false);
        $this->get('https://vpptrangthietbi.com.vn/robots.txt')
            ->assertDontSee('da-gui');
    }
}
