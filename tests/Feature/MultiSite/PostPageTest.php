<?php

namespace Tests\Feature\MultiSite;

use App\Models\Post;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_post_is_rendered_as_full_html_on_its_site(): void
    {
        $site = Site::factory()->create([
            'domain' => 'vpptrangthietbi.com.vn',
            'name' => 'Văn phòng phẩm D&T',
            'theme' => 'vpp',
        ]);
        Post::factory()->for($site)->published()->create([
            'slug' => 'cach-chon-giay-in-a4',
            'title' => 'Cách chọn giấy in A4 cho văn phòng nhà máy',
            'content' => '<p>Định lượng 70gsm hay 80gsm?</p>',
        ]);

        $response = $this->get('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4');

        $response->assertOk();
        $response->assertSee('Cách chọn giấy in A4 cho văn phòng nhà máy');
        $response->assertSee('<p>Định lượng 70gsm hay 80gsm?</p>', escape: false);
        $response->assertSee('Văn phòng phẩm D&amp;T', escape: false);
    }

    public function test_same_slug_on_two_sites_shows_each_sites_own_post(): void
    {
        $vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        $ppe = Site::factory()->create(['domain' => 'ppe.example.vn']);
        Post::factory()->for($vpp)->published()->create(['slug' => 'huong-dan', 'title' => 'Hướng dẫn chọn bút ký']);
        Post::factory()->for($ppe)->published()->create(['slug' => 'huong-dan', 'title' => 'Hướng dẫn chọn găng tay']);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/huong-dan')
            ->assertOk()->assertSee('Hướng dẫn chọn bút ký')->assertDontSee('Hướng dẫn chọn găng tay');

        $this->get('https://ppe.example.vn/kien-thuc/huong-dan')
            ->assertOk()->assertSee('Hướng dẫn chọn găng tay')->assertDontSee('Hướng dẫn chọn bút ký');
    }

    public function test_post_of_one_site_is_never_shown_on_another_site(): void
    {
        $vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Site::factory()->create(['domain' => 'ppe.example.vn']);
        Post::factory()->for($vpp)->published()->create(['slug' => 'giay-in-a4']);

        $this->get('https://ppe.example.vn/kien-thuc/giay-in-a4')->assertNotFound();
    }

    public function test_unknown_host_returns_not_found(): void
    {
        $vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Post::factory()->for($vpp)->published()->create(['slug' => 'giay-in-a4']);

        $this->get('https://khong-ton-tai.vn/kien-thuc/giay-in-a4')->assertNotFound();
    }

    public function test_inactive_site_returns_not_found(): void
    {
        $site = Site::factory()->inactive()->create(['domain' => 'tool.example.vn']);
        Post::factory()->for($site)->published()->create(['slug' => 'co-le']);

        $this->get('https://tool.example.vn/kien-thuc/co-le')->assertNotFound();
    }

    public function test_draft_scheduled_and_deleted_posts_are_not_public(): void
    {
        $site = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Post::factory()->for($site)->create(['slug' => 'ban-nhap']);
        Post::factory()->for($site)->published()->create(['slug' => 'hen-gio', 'published_at' => now()->addHour()]);
        Post::factory()->for($site)->published()->create(['slug' => 'da-xoa'])->delete();

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/ban-nhap')->assertNotFound();
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/hen-gio')->assertNotFound();
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/da-xoa')->assertNotFound();
    }

    public function test_scheduled_post_becomes_public_when_its_time_comes(): void
    {
        $site = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Post::factory()->for($site)->published()->create([
            'slug' => 'hen-gio',
            'title' => 'Bài hẹn giờ',
            'published_at' => now()->addHour(),
        ]);

        $this->travel(2)->hours();

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/hen-gio')->assertOk()->assertSee('Bài hẹn giờ');
    }
}
