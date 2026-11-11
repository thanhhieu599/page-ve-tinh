<?php

namespace Tests\Feature\PublicSite;

use App\Enums\PageKind;
use App\Filament\Resources\RequiredPages\Pages\EditRequiredPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\RequiredPage;
use App\Models\Site;
use App\Models\User;
use App\Support\Indexing\IndexReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

/**
 * Trang giới thiệu kiểu web thương mại (grilling 11/11/2026): banner + số liệu + về chúng tôi +
 * nhóm hàng (chọn từ danh mục) + cam kết có biểu tượng + khách hàng (logo/tên) + thông tin công ty + mời báo giá.
 * Nội dung nhập bằng ô riêng, không còn ô soạn thảo dài. Mỗi site nội dung riêng, cùng bố cục.
 */
class AboutPageTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn', 'name' => 'Văn phòng phẩm D&T']);
    }

    private function about(?Site $site = null): RequiredPage
    {
        return RequiredPage::withoutGlobalScopes()->where('site_id', ($site ?? $this->vpp)->id)->where('kind', PageKind::About)->firstOrFail();
    }

    private function editor(): void
    {
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());
    }

    /** Đoạn "Về chúng tôi" đủ 80 chữ. */
    private static function intro(): string
    {
        return trim(str_repeat('D&T cung cấp văn phòng phẩm và vật tư tiêu hao cho nhà máy FDI tại các khu công nghiệp phía Nam. ', 5));
    }

    /** @return list<array{icon: string, title: string, description: string}> */
    private static function values(int $count = 3): array
    {
        return array_slice([
            ['icon' => 'truck', 'title' => 'Giao đúng hẹn', 'description' => 'Lịch giao cố định cho đơn định kỳ.'],
            ['icon' => 'invoice', 'title' => 'Hoá đơn VAT đầy đủ', 'description' => 'Chứng từ rõ nguồn gốc.'],
            ['icon' => 'shield', 'title' => 'Đúng tiêu chuẩn', 'description' => 'Hàng chính hãng.'],
            ['icon' => 'headset', 'title' => 'Hỗ trợ nhanh', 'description' => 'Phản hồi trong giờ làm việc.'],
        ], 0, $count);
    }

    public function test_an_editor_builds_the_commercial_about_page_block_by_block(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['name' => 'Giấy văn phòng', 'slug' => 'giay-van-phong']);
        $pens = Category::factory()->for($this->vpp)->create(['name' => 'Bút viết', 'slug' => 'but-viet']);
        Product::factory()->for($this->vpp)->count(2)->create(['category_id' => $paper->id]);
        $this->editor();

        Livewire::test(EditRequiredPage::class, ['record' => $this->about()->getRouteKey()])
            ->assertFormFieldHidden('content')
            ->assertFormFieldDoesNotExist('extras.milestones')
            ->assertFormFieldDoesNotExist('extras.gallery')
            ->fillForm([
                'title' => 'Giới thiệu công ty',
                'is_published' => true,
                'extras' => [
                    'hero_image' => [UploadedFile::fake()->image('kho.jpg', 1600, 600)],
                    'headline' => 'Đối tác cung ứng văn phòng phẩm cho nhà máy FDI',
                    'summary' => 'Một đầu mối cho văn phòng và xưởng sản xuất.',
                    'stats' => [['value' => '10+', 'label' => 'năm kinh nghiệm'], ['value' => '500+', 'label' => 'doanh nghiệp tin dùng']],
                    'intro' => self::intro(),
                    'intro_image' => [UploadedFile::fake()->image('doi-ngu.jpg', 800, 600)],
                    'category_ids' => [(string) $paper->id, (string) $pens->id],
                    'values' => self::values(3),
                    'clients' => [
                        ['name' => 'Samsung', 'logo' => [UploadedFile::fake()->image('samsung.png', 200, 80)]],
                        ['name' => 'Intel', 'logo' => []],
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $html = (string) $this->get('https://vpptrangthietbi.com.vn/gioi-thieu')->assertOk()->getContent();

        // 1. Banner: câu định vị là H1, ảnh tải ưu tiên, 2 nút.
        $this->assertMatchesRegularExpression('#<section class="about-banner".*?<img src="/storage/page-images/[^"]+" alt="" width="1600" height="600" fetchpriority="high"#s', $html);
        $this->assertStringContainsString('<h1>Đối tác cung ứng văn phòng phẩm cho nhà máy FDI</h1>', $html);
        $this->assertStringContainsString('Một đầu mối cho văn phòng và xưởng sản xuất.', $html);
        $this->assertStringContainsString('href="/yeu-cau-bao-gia?tu=/gioi-thieu"', $html);
        $this->assertStringContainsString('>Xem sản phẩm</a>', $html);
        // 2–3. Số liệu, về chúng tôi.
        $this->assertMatchesRegularExpression('#10\+.*năm kinh nghiệm.*500\+.*doanh nghiệp tin dùng#s', $html);
        $this->assertStringContainsString('cung cấp văn phòng phẩm và vật tư tiêu hao cho nhà máy FDI', $html);
        $this->assertMatchesRegularExpression('#<img src="/storage/page-images/[^"]+" alt="Về Văn phòng phẩm D&amp;T" width="800" height="600" loading="lazy"#', $html);
        // 4. Nhóm hàng lấy từ danh mục đã chọn, đúng thứ tự.
        $this->assertSame(1, preg_match('#<section[^>]*aria-labelledby="cung-cap".*?</section>#s', $html, $supply));
        $this->assertMatchesRegularExpression('#href="/danh-muc/giay-van-phong".*Giấy văn phòng.*2 sản phẩm.*href="/danh-muc/but-viet".*Bút viết#s', $supply[0]);
        // 5. Cam kết có biểu tượng.
        $this->assertSame(1, preg_match('#<section[^>]*aria-labelledby="vi-sao-chon".*?</section>#s', $html, $why));
        $this->assertSame(3, substr_count($why[0], '<svg'));
        $this->assertStringContainsString('Hoá đơn VAT đầy đủ', $why[0]);
        // 6. Khách hàng: logo có ảnh, chưa có logo thì hiện tên.
        $this->assertMatchesRegularExpression('#<img src="/storage/page-images/[^"]+" alt="Samsung"#', $html);
        $this->assertMatchesRegularExpression('#<span class="about-client-name">Intel</span>#', $html);
        // 7. Thông tin công ty + mời báo giá. Không còn khối cũ.
        $this->assertStringContainsString('Thông tin công ty', $html);
        $this->assertStringNotContainsString('about-timeline', $html);
        $this->assertStringNotContainsString('about-gallery', $html);
    }

    public function test_without_a_banner_picture_the_banner_is_dark_with_white_text(): void
    {
        $this->about()->forceFill(['is_published' => true, 'extras' => ['headline' => 'Đối tác cung ứng cho nhà máy']])->save();

        $html = (string) $this->get('https://vpptrangthietbi.com.vn/gioi-thieu')->assertOk()->getContent();

        $this->assertStringContainsString('<section class="about-banner about-banner-plain"', $html);
        $this->assertStringContainsString('<h1>Đối tác cung ứng cho nhà máy</h1>', $html);
        $this->assertStringNotContainsString('aria-labelledby="cung-cap"', $html, 'Chưa chọn danh mục thì ẩn khối nhóm hàng.');
    }

    public function test_the_page_cannot_be_published_until_headline_intro_and_three_commitments_are_filled(): void
    {
        $this->editor();

        Livewire::test(EditRequiredPage::class, ['record' => $this->about()->getRouteKey()])
            ->fillForm(['is_published' => true, 'extras' => ['headline' => '', 'intro' => 'Quá ngắn.', 'values' => self::values(2)]])
            ->call('save')
            ->assertHasFormErrors(['extras.headline', 'extras.intro', 'extras.values']);

        $this->assertFalse($this->about()->is_published);

        Livewire::test(EditRequiredPage::class, ['record' => $this->about()->getRouteKey()])
            ->fillForm(['is_published' => true, 'extras' => ['headline' => 'Đối tác cung ứng', 'intro' => self::intro(), 'values' => self::values(3)]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->about()->is_published);
    }

    public function test_at_most_six_product_groups(): void
    {
        $ids = Category::factory()->for($this->vpp)->count(7)->create()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->editor();

        Livewire::test(EditRequiredPage::class, ['record' => $this->about()->getRouteKey()])
            ->fillForm(['extras' => ['category_ids' => $ids]])
            ->call('save')
            ->assertHasFormErrors(['extras.category_ids']);
    }

    public function test_an_incomplete_about_page_keeps_the_site_out_of_google(): void
    {
        $this->about()->forceFill(['is_published' => true, 'extras' => ['headline' => 'Có câu định vị nhưng thiếu phần còn lại']])->save();

        $item = collect(IndexReadiness::for($this->vpp)->items)->first(fn ($item) => $item->label === PageKind::About->readinessLabel());

        $this->assertFalse($item->passed);
        $this->assertStringContainsString('Về chúng tôi', $item->detail);
    }

    public function test_each_site_keeps_its_own_about_content(): void
    {
        $ppe = Site::factory()->create(['domain' => 'ppetrangthietbi.vn', 'name' => 'PPE D&T']);
        $this->about()->forceFill(['is_published' => true, 'extras' => ['headline' => 'Văn phòng phẩm cho nhà máy']])->save();
        $this->about($ppe)->forceFill(['is_published' => true, 'extras' => ['headline' => 'Bảo hộ lao động đạt chuẩn']])->save();

        $this->get('https://vpptrangthietbi.com.vn/gioi-thieu')->assertSee('Văn phòng phẩm cho nhà máy')->assertDontSee('Bảo hộ lao động đạt chuẩn');
        $this->get('https://ppetrangthietbi.vn/gioi-thieu')->assertSee('Bảo hộ lao động đạt chuẩn')->assertDontSee('Văn phòng phẩm cho nhà máy');
    }

    public function test_old_about_pages_are_upgraded_to_the_new_blocks(): void
    {
        Storage::disk('public')->put('page-images/cu-1.jpg', 'x');
        $page = $this->about();
        $page->forceFill([
            'content' => '<h2>Về D&amp;T</h2><p>D&amp;T cung cấp văn phòng phẩm cho nhà máy.</p><h2>Năng lực</h2><p>[CẦN BỔ SUNG: …]</p>',
            'extras' => [
                'milestones' => [['year' => '2015', 'title' => 'Thành lập']],
                'gallery' => ['page-images/cu-1.jpg'],
                'clients' => ['Samsung', 'Intel'],
                'values' => [['title' => 'Đúng chuẩn', 'description' => 'Hàng chính hãng.']],
            ],
        ])->saveQuietly();

        RequiredPage::upgradeAboutPages();

        $extras = $page->fresh()->extras;
        $this->assertSame('D&T cung cấp văn phòng phẩm cho nhà máy.', $extras['intro']);
        $this->assertSame([['name' => 'Samsung'], ['name' => 'Intel']], $extras['clients']);
        $this->assertSame('shield', $extras['values'][0]['icon']);
        $this->assertArrayNotHasKey('milestones', $extras);
        $this->assertArrayNotHasKey('gallery', $extras);
        Storage::disk('public')->assertMissing('page-images/cu-1.jpg');
    }

    public function test_replaced_pictures_are_removed_from_storage(): void
    {
        Storage::disk('public')->put('page-images/cu.jpg', 'x');
        $page = $this->about();
        $page->forceFill(['extras' => ['hero_image' => 'page-images/cu.jpg']])->save();

        $page->forceFill(['extras' => ['hero_image' => null]])->save();

        Storage::disk('public')->assertMissing('page-images/cu.jpg');
    }

    public function test_other_pages_keep_the_text_editor(): void
    {
        $this->editor();
        $privacy = RequiredPage::withoutGlobalScopes()->where('site_id', $this->vpp->id)->where('kind', PageKind::Privacy)->firstOrFail();

        Livewire::test(EditRequiredPage::class, ['record' => $privacy->getRouteKey()])
            ->assertFormFieldVisible('content')
            ->assertFormFieldHidden('extras.headline');
    }
}
