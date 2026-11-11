<?php

namespace Tests\Feature\PublicSite;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Site;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsAndLayoutTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    public function test_the_news_page_shows_cards_with_picture_date_and_category(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['name' => 'Giấy văn phòng']);
        $file = UploadedFile::fake()->image('a.jpg', 1200, 800);
        $media = MediaLibrary::store($file->getRealPath(), 'Kho giấy', $this->vpp->id);
        Post::factory()->for($this->vpp)->create([
            'title' => 'Giá giấy A4 tháng 11 tăng nhẹ', 'status' => PostStatus::Published,
            'published_at' => Carbon::parse('2026-11-09 03:00:00', 'UTC'),
            'content' => '<p>Tin.</p><p><img src="'.$media->url().'" alt=""></p>',
        ])->categories()->attach($paper);

        $this->get('https://vpptrangthietbi.com.vn/tin-tuc')
            ->assertOk()
            ->assertSee('<title>Tin tức', escape: false)
            ->assertSee('<link rel="canonical" href="https://vpptrangthietbi.com.vn/tin-tuc">', escape: false)
            ->assertSeeText('Giá giấy A4 tháng 11 tăng nhẹ')
            ->assertSeeText('09/11/2026')
            ->assertSeeText('Giấy văn phòng')
            ->assertSee('src="'.$media->url(480).'"', escape: false);
    }

    public function test_the_home_page_news_block_shows_each_post_picture(): void
    {
        $file = UploadedFile::fake()->image('a.jpg', 1200, 800);
        $media = MediaLibrary::store($file->getRealPath(), 'Kho giấy', $this->vpp->id);
        Post::factory()->for($this->vpp)->published()->create([
            'title' => 'Giá giấy A4 tháng 11 tăng nhẹ',
            'content' => '<p>Tin.</p><p><img src="'.$media->url().'" alt=""></p>',
        ]);

        $html = (string) $this->get('https://vpptrangthietbi.com.vn/')->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<section[^>]*aria-labelledby="tin-tuc".*?</section>#s', $html, $news));
        $this->assertStringContainsString('src="'.$media->url(480).'"', $news[0]);
        $this->assertStringContainsString('Giá giấy A4 tháng 11 tăng nhẹ', $news[0]);
    }

    public function test_the_old_articles_address_moves_permanently_to_the_news_page(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc')->assertRedirect('/tin-tuc')->assertStatus(301);
    }

    public function test_menu_and_footer_point_to_news(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')
            ->assertSee('<a href="/tin-tuc">Tin tức</a>', escape: false)
            ->assertDontSee('href="/kien-thuc"', escape: false);
    }

    public function test_an_article_page_offers_related_news_and_featured_products_beside_it(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['name' => 'Giấy']);
        $post = Post::factory()->for($this->vpp)->published()->create(['slug' => 'bai-chinh', 'title' => 'Bài chính']);
        $post->categories()->attach($paper);
        Post::factory()->for($this->vpp)->published()->create(['title' => 'Tin cùng chủ đề'])->categories()->attach($paper);
        Post::factory()->for($this->vpp)->published()->create(['title' => 'Tin chủ đề khác']);
        Product::factory()->for($this->vpp)->create(['name' => 'Giấy Double A nổi bật', 'is_featured' => true]);

        $html = (string) $this->get('https://vpptrangthietbi.com.vn/kien-thuc/bai-chinh')->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<section[^>]*aria-labelledby="tin-lien-quan".*?</section>#s', $html, $related));
        $this->assertStringContainsString('Tin cùng chủ đề', $related[0]);
        $this->assertStringNotContainsString('Tin chủ đề khác', $related[0]);
        $this->assertStringNotContainsString('>Bài chính<', $related[0]);
        $this->assertSame(1, preg_match('#<aside.*?</aside>#s', $html, $aside));
        $this->assertStringContainsString('Giấy Double A nổi bật', $aside[0]);
        $this->assertStringContainsString('href="/yeu-cau-bao-gia"', $aside[0]);
    }
}
