<?php

namespace Tests\Feature\Seo;

use App\Enums\PostStatus;
use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    /** @return list<string> */
    private function locs(TestResponse $response): array
    {
        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml, 'sitemap.xml phải là XML hợp lệ.');

        $locs = [];
        foreach ($xml->url as $url) {
            $locs[] = (string) $url->loc;
        }
        sort($locs);

        return $locs;
    }

    public function test_sitemap_lists_only_live_urls_of_this_site(): void
    {
        $author = Author::factory()->create(['slug' => 'bien-tap-dt']);
        Author::factory()->create(['slug' => 'chua-viet-bai']);
        $paper = Category::factory()->for($this->vpp)->create(['slug' => 'giay-van-phong']);
        $a4 = Category::factory()->for($this->vpp)->create(['slug' => 'giay-a4', 'parent_id' => $paper->id]);
        Category::factory()->for($this->vpp)->create(['slug' => 'danh-muc-trong']);

        $live = Post::factory()->for($this->vpp)->published()->create(['slug' => 'cach-chon-giay-in-a4', 'author_id' => $author->id]);
        $live->categories()->attach($a4);
        Post::factory()->for($this->vpp)->create(['slug' => 'bai-nhap', 'author_id' => $author->id]);
        Post::factory()->for($this->vpp)->create(['slug' => 'bai-cho-duyet', 'status' => PostStatus::Pending]);
        Post::factory()->for($this->vpp)->create([
            'slug' => 'bai-hen-gio', 'status' => PostStatus::Published, 'published_at' => now()->addDay(),
        ]);
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'bai-da-xoa'])->delete();

        Product::factory()->for($this->vpp)->create(['slug' => 'giay-double-a-a4-80gsm']);
        Product::factory()->for($this->vpp)->create(['slug' => 'giay-ngung-ban', 'is_active' => false]);

        $other = Site::factory()->create(['domain' => 'ppetrangthietbi.vn']);
        Post::factory()->for($other)->published()->create(['slug' => 'gang-tay-towa']);
        Product::factory()->for($other)->create(['slug' => 'gang-tay-towa-370']);

        $response = $this->get('https://vpptrangthietbi.com.vn/sitemap.xml')->assertOk();

        $this->assertStringStartsWith('text/xml', (string) $response->headers->get('Content-Type'));
        $this->assertSame([
            'https://vpptrangthietbi.com.vn/',
            'https://vpptrangthietbi.com.vn/danh-muc/giay-a4',
            'https://vpptrangthietbi.com.vn/danh-muc/giay-van-phong',
            'https://vpptrangthietbi.com.vn/kien-thuc',
            'https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4',
            'https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm',
            'https://vpptrangthietbi.com.vn/tac-gia/bien-tap-dt',
        ], $this->locs($response));

        $this->assertSame([
            'https://ppetrangthietbi.vn/',
            'https://ppetrangthietbi.vn/kien-thuc',
            'https://ppetrangthietbi.vn/kien-thuc/gang-tay-towa',
            'https://ppetrangthietbi.vn/san-pham/gang-tay-towa-370',
        ], $this->locs($this->get('https://ppetrangthietbi.vn/sitemap.xml')));
    }

    public function test_sitemap_dates_each_post_by_its_last_change(): void
    {
        $this->travelTo('2026-11-05 10:00:00');
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'cach-chon-giay-in-a4']);

        $this->travelTo('2026-11-10 10:00:00');
        $xml = simplexml_load_string((string) $this->get('https://vpptrangthietbi.com.vn/sitemap.xml')->getContent());

        $post = $xml->xpath('//*[local-name()="url"][*[local-name()="loc"]="https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4"]')[0];
        $this->assertStringStartsWith('2026-11-05', (string) $post->lastmod);
    }
}
