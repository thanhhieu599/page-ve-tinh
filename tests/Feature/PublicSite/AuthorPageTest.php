<?php

namespace Tests\Feature\PublicSite;

use App\Models\Author;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_page_shows_profile_and_only_this_sites_published_posts(): void
    {
        $vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        $ppe = Site::factory()->create(['domain' => 'ppe.example.vn']);
        $author = Author::factory()->create([
            'slug' => 'nguyen-van-an',
            'name' => 'Nguyễn Văn An',
            'job_title' => 'Chuyên viên EHS',
            'bio' => '12 năm làm an toàn lao động tại nhà máy FDI.',
            'expertise' => 'ISO 45001, đánh giá rủi ro',
            'linkedin_url' => 'https://www.linkedin.com/in/nguyen-van-an',
        ]);
        Post::factory()->for($vpp)->for($author)->published()->create(['title' => 'Chọn bìa hồ sơ ISO']);
        Post::factory()->for($vpp)->for($author)->create(['title' => 'Bài nháp của tác giả']);
        Post::factory()->for($ppe)->for($author)->published()->create(['title' => 'Găng tay EN 388 ở site khác']);

        $this->get('https://vpptrangthietbi.com.vn/tac-gia/nguyen-van-an')
            ->assertOk()
            ->assertSee('Nguyễn Văn An')
            ->assertSee('Chuyên viên EHS')
            ->assertSee('12 năm làm an toàn lao động tại nhà máy FDI.')
            ->assertSee('ISO 45001, đánh giá rủi ro')
            ->assertSee('href="https://www.linkedin.com/in/nguyen-van-an"', escape: false)
            ->assertSee('Chọn bìa hồ sơ ISO')
            ->assertDontSee('Bài nháp của tác giả')
            ->assertDontSee('Găng tay EN 388 ở site khác');
    }

    public function test_post_page_names_its_author_with_a_link_to_the_profile(): void
    {
        $vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        $author = Author::factory()->create(['slug' => 'tran-thi-binh', 'name' => 'Trần Thị Bình']);
        Post::factory()->for($vpp)->for($author)->published()->create(['slug' => 'muc-in']);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/muc-in')
            ->assertSee('<a href="/tac-gia/tran-thi-binh" rel="author">Trần Thị Bình</a>', escape: false);
    }

    public function test_unknown_author_is_not_found(): void
    {
        Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);

        $this->get('https://vpptrangthietbi.com.vn/tac-gia/khong-co')->assertNotFound();
    }
}
