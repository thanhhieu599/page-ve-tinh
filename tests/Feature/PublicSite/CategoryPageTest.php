<?php

namespace Tests\Feature\PublicSite;

use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPageTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    public function test_category_page_lists_published_posts_of_the_category_and_its_children(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['slug' => 'giay', 'name' => 'Giấy văn phòng']);
        $a4 = Category::factory()->for($this->vpp)->childOf($paper)->create(['slug' => 'giay-a4', 'name' => 'Giấy A4']);

        Post::factory()->for($this->vpp)->published()->hasAttached($paper)->create(['title' => 'Giấy in hoá đơn nhiệt']);
        Post::factory()->for($this->vpp)->published()->hasAttached($a4)->create(['title' => 'Giấy A4 70gsm hay 80gsm']);
        Post::factory()->for($this->vpp)->hasAttached($paper)->create(['title' => 'Bài nháp về giấy']);
        Post::factory()->for($this->vpp)->published()->create(['title' => 'Bút ký không thuộc danh mục']);

        $this->get('https://vpptrangthietbi.com.vn/danh-muc/giay')
            ->assertOk()
            ->assertSee('Giấy văn phòng')
            ->assertSee('Giấy in hoá đơn nhiệt')
            ->assertSee('Giấy A4 70gsm hay 80gsm')
            ->assertSee('href="/kien-thuc/', escape: false)
            ->assertDontSee('Bài nháp về giấy')
            ->assertDontSee('Bút ký không thuộc danh mục');
    }

    public function test_category_of_another_site_is_not_found(): void
    {
        $ppe = Site::factory()->create(['domain' => 'ppe.example.vn']);
        Category::factory()->for($ppe)->create(['slug' => 'gang-tay']);

        $this->get('https://vpptrangthietbi.com.vn/danh-muc/gang-tay')->assertNotFound();
    }

    public function test_long_category_is_paginated(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['slug' => 'giay']);
        foreach (range(1, 13) as $i) {
            Post::factory()->for($this->vpp)->published()->hasAttached($paper)
                ->create(['title' => "Bài số {$i}", 'published_at' => now()->subDays($i)]);
        }

        $this->get('https://vpptrangthietbi.com.vn/danh-muc/giay')
            ->assertSee('Bài số 1<', escape: false)->assertDontSee('Bài số 13<', escape: false)
            ->assertSee('?page=2', escape: false);

        $this->get('https://vpptrangthietbi.com.vn/danh-muc/giay?page=2')
            ->assertOk()->assertSee('Bài số 13<', escape: false);
    }
}
