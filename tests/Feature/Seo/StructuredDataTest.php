<?php

namespace Tests\Feature\Seo;

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Site;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'company.name' => 'D&T',
            'company.legal_name' => 'Công ty TNHH TMDV Phát triển Kỹ thuật D & T',
            'company.url' => 'https://trangthietbi.vn',
        ]);
        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    /**
     * Mọi khối JSON-LD trên trang, gom theo @type.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function jsonLd(TestResponse $response): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', (string) $response->getContent(), $matches);
        $this->assertNotEmpty($matches[1], 'Trang phải có JSON-LD.');

        $byType = [];
        foreach ($matches[1] as $json) {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('https://schema.org', $data['@context'] ?? null);
            foreach ($data['@graph'] ?? [$data] as $node) {
                $byType[$node['@type']][] = $node;
            }
        }

        return $byType;
    }

    /** @return array<string, mixed> */
    private function one(TestResponse $response, string $type): array
    {
        $nodes = $this->jsonLd($response)[$type] ?? [];
        $this->assertCount(1, $nodes, "Trang phải có đúng một {$type}.");

        return $nodes[0];
    }

    public function test_a_post_is_described_as_an_article_by_its_author_and_published_by_dt(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 03:00:00', 'UTC'));
        $author = Author::factory()->create(['name' => 'Ban biên tập D&T', 'slug' => 'bien-tap-dt']);
        $post = Post::factory()->for($this->vpp)->create([
            'slug' => 'cach-chon-giay-in-a4',
            'title' => 'Cách chọn giấy in A4 cho văn phòng nhà máy',
            'excerpt' => 'Định lượng 70gsm phù hợp in nội bộ.',
            'og_image' => 'og-images/giay-a4.webp',
            'author_id' => $author->id,
        ]);
        $post->publish();
        $this->travelTo(Carbon::parse('2026-11-05 10:30:00', 'UTC'));
        $post->update(['excerpt' => 'Định lượng 70gsm phù hợp in nội bộ, 80gsm cho hồ sơ gửi khách hàng.']);

        $article = $this->one($this->get('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4'), 'Article');

        $this->assertSame('Cách chọn giấy in A4 cho văn phòng nhà máy', $article['headline']);
        $this->assertSame('Định lượng 70gsm phù hợp in nội bộ, 80gsm cho hồ sơ gửi khách hàng.', $article['description']);
        $this->assertSame('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4', $article['mainEntityOfPage']);
        $this->assertSame(['https://vpptrangthietbi.com.vn/storage/og-images/giay-a4.webp'], $article['image']);
        $this->assertSame('2026-11-01T10:00:00+07:00', $article['datePublished']);
        $this->assertSame('2026-11-05T17:30:00+07:00', $article['dateModified']);
        $this->assertSame([
            '@type' => 'Person',
            'name' => 'Ban biên tập D&T',
            'url' => 'https://vpptrangthietbi.com.vn/tac-gia/bien-tap-dt',
        ], $article['author']);
        $this->assertSame('Organization', $article['publisher']['@type']);
        $this->assertSame('Công ty TNHH TMDV Phát triển Kỹ thuật D & T', $article['publisher']['name']);
        $this->assertSame('https://trangthietbi.vn/#organization', $article['publisher']['@id']);
    }

    public function test_every_page_names_dt_as_the_same_organization_on_every_site(): void
    {
        config([
            'company.logo' => 'https://trangthietbi.vn/logo.png',
            'company.tax_id' => '0312345678',
            'company.phone' => '+84 28 1234 5678',
            'company.email' => 'sales@trangthietbi.vn',
            'company.street_address' => '12 Đường Số 7, KCN Tân Bình',
            'company.locality' => 'Quận Tân Phú',
            'company.region' => 'TP. Hồ Chí Minh',
        ]);
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'cach-chon-giay-in-a4']);
        $ppe = Site::factory()->create(['domain' => 'ppetrangthietbi.vn']);
        Post::factory()->for($ppe)->published()->create(['slug' => 'chon-gang-tay-towa']);

        $expected = [
            '@type' => 'Organization',
            '@id' => 'https://trangthietbi.vn/#organization',
            'name' => 'Công ty TNHH TMDV Phát triển Kỹ thuật D & T',
            'alternateName' => 'D&T',
            'url' => 'https://trangthietbi.vn',
            'logo' => 'https://trangthietbi.vn/logo.png',
            'taxID' => '0312345678',
            'telephone' => '+84 28 1234 5678',
            'email' => 'sales@trangthietbi.vn',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => '12 Đường Số 7, KCN Tân Bình',
                'addressLocality' => 'Quận Tân Phú',
                'addressRegion' => 'TP. Hồ Chí Minh',
                'addressCountry' => 'VN',
            ],
        ];

        foreach ([
            'https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4',
            'https://vpptrangthietbi.com.vn/yeu-cau-bao-gia',
            'https://ppetrangthietbi.vn/kien-thuc/chon-gang-tay-towa',
        ] as $url) {
            $organization = $this->one($this->get($url), 'Organization');
            unset($organization['@context']);
            $this->assertEquals($expected, $organization, $url);
        }
    }

    public function test_company_details_left_blank_are_left_out_of_the_organization(): void
    {
        $organization = $this->one($this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia'), 'Organization');

        $this->assertSame('https://trangthietbi.vn', $organization['url']);
        foreach (['logo', 'taxID', 'telephone', 'email', 'address'] as $blank) {
            $this->assertArrayNotHasKey($blank, $organization);
        }
    }

    public function test_faq_schema_matches_the_questions_and_answers_shown_on_the_post(): void
    {
        $post = Post::factory()->for($this->vpp)->published()->create(['slug' => 'cach-chon-giay-in-a4']);
        $post->faqs()->create(['question' => 'Nên chọn giấy 70gsm hay 80gsm?', 'answer' => "In nội bộ: 70gsm.\nHồ sơ gửi khách: 80gsm.", 'sort' => 2]);
        $post->faqs()->create(['question' => 'Giấy A4 có cần chống ẩm không?', 'answer' => 'Có, nhất là kho gần xưởng sản xuất.', 'sort' => 1]);

        $response = $this->get('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4')
            ->assertSeeInOrder(['Giấy A4 có cần chống ẩm không?', 'Nên chọn giấy 70gsm hay 80gsm?']);

        $this->assertSame([
            [
                '@type' => 'Question',
                'name' => 'Giấy A4 có cần chống ẩm không?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Có, nhất là kho gần xưởng sản xuất.'],
            ],
            [
                '@type' => 'Question',
                'name' => 'Nên chọn giấy 70gsm hay 80gsm?',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => "In nội bộ: 70gsm.\nHồ sơ gửi khách: 80gsm."],
            ],
        ], $this->one($response, 'FAQPage')['mainEntity']);
    }

    public function test_a_post_without_questions_has_no_faq_schema(): void
    {
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'cach-chon-giay-in-a4']);

        $this->assertArrayNotHasKey('FAQPage', $this->jsonLd($this->get('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4')));
    }

    private function product(): Product
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('giay.jpg', 1200, 900);
        $path = $file->getRealPath();
        $media = MediaLibrary::store($path, 'Ram giấy Double A A4 80gsm', $this->vpp->id);

        return Product::factory()->for($this->vpp)->create([
            'slug' => 'giay-double-a-a4-80gsm',
            'name' => 'Giấy Double A A4 80gsm',
            'sku' => 'DA-A4-80',
            'description' => 'Giấy in hai mặt, 500 tờ/ram, phù hợp máy in laser tốc độ cao.',
            'media_id' => $media->id,
        ]);
    }

    public function test_a_product_declares_its_price_in_vnd_only_while_the_price_is_fresh(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 08:00:00', 'UTC'));
        $this->product()->setPrice(89000);

        $this->travelTo(Carbon::parse('2026-11-15 08:00:00', 'UTC')); // ngày thứ 14: giá còn hạn
        $product = $this->one($this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm'), 'Product');

        $this->assertSame('Giấy Double A A4 80gsm', $product['name']);
        $this->assertSame('DA-A4-80', $product['sku']);
        $this->assertSame('Giấy in hai mặt, 500 tờ/ram, phù hợp máy in laser tốc độ cao.', $product['description']);
        $this->assertStringStartsWith('https://vpptrangthietbi.com.vn/storage/', $product['image'][0]);
        $this->assertSame('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm', $product['url']);
        $this->assertSame('Offer', $product['offers']['@type']);
        $this->assertSame(89000, $product['offers']['price']);
        $this->assertSame('VND', $product['offers']['priceCurrency']);
        $this->assertSame('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm', $product['offers']['url']);

        $this->travelTo(Carbon::parse('2026-11-16 08:00:00', 'UTC')); // ngày 15: giá hết hạn
        $product = $this->one($this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm'), 'Product');

        $this->assertSame('DA-A4-80', $product['sku']);
        $this->assertArrayNotHasKey('offers', $product);
        $this->assertStringNotContainsString('89000', json_encode($product));
    }

    /** Breadcrumb hiển thị: [nhãn, href|null] theo thứ tự trên trang. */
    private function visibleBreadcrumb(TestResponse $response): array
    {
        preg_match('#<nav aria-label="Breadcrumb".*?</nav>#s', (string) $response->getContent(), $nav);
        preg_match_all('#<li>\s*(?:<a href="([^"]*)">|<span[^>]*>)(.*?)</(?:a|span)>#s', $nav[0], $items, PREG_SET_ORDER);

        return array_map(fn ($item) => [html_entity_decode($item[2]), $item[1] ?: null], $items);
    }

    /** @return list<array{0: string, 1: string}> */
    private function breadcrumbSchema(TestResponse $response): array
    {
        $list = $this->one($response, 'BreadcrumbList')['itemListElement'];

        return array_map(function (array $item, int $index): array {
            $this->assertSame('ListItem', $item['@type']);
            $this->assertSame($index + 1, $item['position']);

            return [$item['name'], $item['item']];
        }, $list, array_keys($list));
    }

    public function test_breadcrumb_schema_follows_the_breadcrumb_shown_on_a_post(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['slug' => 'giay-van-phong', 'name' => 'Giấy văn phòng']);
        $a4 = Category::factory()->for($this->vpp)->childOf($paper)->create(['slug' => 'giay-a4', 'name' => 'Giấy A4']);
        $post = Post::factory()->for($this->vpp)->published()->create([
            'slug' => 'cach-chon-giay-in-a4', 'title' => 'Cách chọn giấy in A4 & 80gsm',
        ]);
        $post->categories()->attach($a4);

        $response = $this->get('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4');

        $this->assertSame([
            ['Trang chủ', '/'],
            ['Giấy văn phòng', '/danh-muc/giay-van-phong'],
            ['Giấy A4', '/danh-muc/giay-a4'],
            ['Cách chọn giấy in A4 & 80gsm', null],
        ], $this->visibleBreadcrumb($response));
        $this->assertSame([
            ['Trang chủ', 'https://vpptrangthietbi.com.vn/'],
            ['Giấy văn phòng', 'https://vpptrangthietbi.com.vn/danh-muc/giay-van-phong'],
            ['Giấy A4', 'https://vpptrangthietbi.com.vn/danh-muc/giay-a4'],
            ['Cách chọn giấy in A4 & 80gsm', 'https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4'],
        ], $this->breadcrumbSchema($response));
    }

    public function test_breadcrumb_schema_on_a_product_page_ends_at_the_product(): void
    {
        Product::factory()->for($this->vpp)->create(['slug' => 'giay-double-a-a4-80gsm', 'name' => 'Giấy Double A A4 80gsm']);

        $this->assertSame([
            ['Trang chủ', 'https://vpptrangthietbi.com.vn/'],
            ['Giấy Double A A4 80gsm', 'https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm'],
        ], $this->breadcrumbSchema($this->get('https://vpptrangthietbi.com.vn/san-pham/giay-double-a-a4-80gsm')));
    }

    public function test_without_a_share_image_the_article_uses_the_first_library_image_of_the_post(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('kho.jpg', 1600, 900);
        $path = $file->getRealPath();
        $media = MediaLibrary::store($path, 'Kho giấy', $this->vpp->id);
        Post::factory()->for($this->vpp)->published()->create([
            'slug' => 'cach-chon-giay-in-a4',
            'content' => '<p>Ảnh ngoài.</p><p><img src="https://cdn.khac.vn/x.jpg" alt=""></p><p><img src="'.$media->url(480).'" alt=""></p>',
        ]);

        $article = $this->one($this->get('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4'), 'Article');

        $this->assertSame(['https://vpptrangthietbi.com.vn'.$media->url()], $article['image']);
    }

    public function test_an_article_without_any_image_declares_none(): void
    {
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'cach-chon-giay-in-a4', 'content' => '<p>Chỉ có chữ.</p>']);

        $this->assertArrayNotHasKey('image', $this->one($this->get('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4'), 'Article'));
    }

    public function test_images_served_from_a_cdn_keep_their_own_address(): void
    {
        Storage::fake('public', ['url' => 'https://cdn.trangthietbi.vn/storage']);
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'cach-chon-giay-in-a4', 'og_image' => 'og-images/giay-a4.webp']);

        $article = $this->one($this->get('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4'), 'Article');

        $this->assertSame(['https://cdn.trangthietbi.vn/storage/og-images/giay-a4.webp'], $article['image']);
    }

    public function test_the_article_publisher_is_the_same_organization_node_as_the_page(): void
    {
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'cach-chon-giay-in-a4']);
        $response = $this->get('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4');

        $organization = $this->one($response, 'Organization');
        $article = $this->one($response, 'Article');

        $this->assertSame('https://trangthietbi.vn/#organization', $organization['@id']);
        $this->assertSame('https://trangthietbi.vn/#organization', $article['publisher']['@id']);
        $this->assertSame('Công ty TNHH TMDV Phát triển Kỹ thuật D & T', $article['publisher']['name']);
    }

    public function test_an_article_without_summary_declares_no_empty_description(): void
    {
        Post::factory()->for($this->vpp)->published()->create(['slug' => 'cach-chon-giay-in-a4', 'excerpt' => null, 'meta_description' => null]);

        $this->assertArrayNotHasKey('description', $this->one($this->get('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4'), 'Article'));
    }

    public function test_a_logo_that_is_not_an_https_address_is_left_out(): void
    {
        config(['company.logo' => 'logo.png']);

        $this->assertArrayNotHasKey('logo', $this->one($this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia'), 'Organization'));
    }
}
