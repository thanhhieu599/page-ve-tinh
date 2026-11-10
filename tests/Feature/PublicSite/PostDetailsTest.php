<?php

namespace Tests\Feature\PublicSite;

use App\Models\Category;
use App\Models\Faq;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostDetailsTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    public function test_faqs_are_shown_in_their_sort_order(): void
    {
        $post = Post::factory()->for($this->vpp)->published()->create(['slug' => 'giay-in']);
        Faq::factory()->for($post)->create(['question' => 'Câu hỏi thứ hai?', 'answer' => 'Trả lời hai.', 'sort' => 2]);
        Faq::factory()->for($post)->create(['question' => 'Câu hỏi thứ nhất?', 'answer' => 'Trả lời một.', 'sort' => 1]);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-in')
            ->assertOk()
            ->assertSeeInOrder(['Câu hỏi thường gặp', 'Câu hỏi thứ nhất?', 'Trả lời một.', 'Câu hỏi thứ hai?', 'Trả lời hai.']);
    }

    public function test_post_without_faqs_shows_no_faq_section(): void
    {
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'giay-in']);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-in')->assertDontSee('Câu hỏi thường gặp');
    }

    public function test_breadcrumb_goes_from_home_through_category_parents_to_the_post(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['slug' => 'giay', 'name' => 'Giấy văn phòng']);
        $a4 = Category::factory()->for($this->vpp)->childOf($paper)->create(['slug' => 'giay-a4', 'name' => 'Giấy A4']);
        Post::factory()->for($this->vpp)->published()->hasAttached($a4)
            ->create(['slug' => 'giay-a4-70gsm', 'title' => 'Giấy A4 70gsm']);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-a4-70gsm')
            ->assertSeeInOrder([
                '<nav aria-label="Breadcrumb"',
                '<a href="/">Trang chủ</a>',
                '<a href="/danh-muc/giay">Giấy văn phòng</a>',
                '<a href="/danh-muc/giay-a4">Giấy A4</a>',
                'Giấy A4 70gsm',
            ], escape: false);
    }

    public function test_category_page_has_a_breadcrumb_too(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['slug' => 'giay', 'name' => 'Giấy văn phòng']);
        Category::factory()->for($this->vpp)->childOf($paper)->create(['slug' => 'giay-a4', 'name' => 'Giấy A4']);

        $this->get('https://vpptrangthietbi.com.vn/danh-muc/giay-a4')
            ->assertSeeInOrder(['<a href="/">Trang chủ</a>', '<a href="/danh-muc/giay">Giấy văn phòng</a>', 'Giấy A4'], escape: false);
    }

    public function test_breadcrumb_uses_the_first_category_attached_to_the_post(): void
    {
        $first = Category::factory()->for($this->vpp)->create(['slug' => 'but', 'name' => 'Bút viết']);
        $second = Category::factory()->for($this->vpp)->create(['slug' => 'qua-tang', 'name' => 'Quà tặng']);
        $post = Post::factory()->for($this->vpp)->published()->create(['slug' => 'but-ky-khac-ten']);
        $post->categories()->attach($first);
        $post->categories()->attach($second);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/but-ky-khac-ten')
            ->assertSee('<a href="/danh-muc/but">Bút viết</a>', escape: false)
            ->assertDontSee('<a href="/danh-muc/qua-tang">', escape: false);
    }
}
