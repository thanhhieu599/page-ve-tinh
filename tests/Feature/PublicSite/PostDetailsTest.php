<?php

namespace Tests\Feature\PublicSite;

use App\Models\Author;
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

    public function test_author_page_has_a_breadcrumb(): void
    {
        Author::factory()->create(['slug' => 'nguyen-van-an', 'name' => 'Nguyễn Văn An']);

        $this->get('https://vpptrangthietbi.com.vn/tac-gia/nguyen-van-an')
            ->assertSeeInOrder(['<nav aria-label="Breadcrumb"', '<a href="/">Trang chủ</a>', 'Nguyễn Văn An'], escape: false);
    }

    public function test_meta_description_is_rendered_for_posts_and_categories(): void
    {
        Category::factory()->for($this->vpp)->create(['slug' => 'giay', 'meta_description' => 'Giấy văn phòng cho nhà máy FDI.']);
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'giay-in', 'meta_description' => 'Cách chọn giấy in A4.']);

        $this->get('https://vpptrangthietbi.com.vn/danh-muc/giay')
            ->assertSee('<meta name="description" content="Giấy văn phòng cho nhà máy FDI.">', escape: false);
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-in')
            ->assertSee('<meta name="description" content="Cách chọn giấy in A4.">', escape: false);
    }

    public function test_category_page_never_lists_a_post_of_another_site_even_if_linked(): void
    {
        $ppe = Site::factory()->create(['domain' => 'ppe.example.vn']);
        $paper = Category::factory()->for($this->vpp)->create(['slug' => 'giay']);
        $foreign = Post::factory()->for($ppe)->published()->create(['title' => 'Bài của site PPE']);
        $foreign->categories()->attach($paper);

        $this->get('https://vpptrangthietbi.com.vn/danh-muc/giay')->assertOk()->assertDontSee('Bài của site PPE');
    }

    public function test_no_empty_meta_description_tag_when_there_is_nothing_to_say(): void
    {
        Category::factory()->for($this->vpp)->create(['slug' => 'giay', 'meta_description' => null]);

        $this->get('https://vpptrangthietbi.com.vn/danh-muc/giay')->assertDontSee('name="description"', escape: false);
    }

    public function test_meta_description_is_escaped(): void
    {
        Category::factory()->for($this->vpp)->create(['slug' => 'giay', 'meta_description' => '"><script>alert(1)</script>']);

        $this->get('https://vpptrangthietbi.com.vn/danh-muc/giay')->assertDontSee('<script>alert(1)', escape: false);
    }
}
