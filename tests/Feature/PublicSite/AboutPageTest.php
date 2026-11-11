<?php

namespace Tests\Feature\PublicSite;

use App\Enums\PageKind;
use App\Filament\Resources\RequiredPages\Pages\EditRequiredPage;
use App\Models\RequiredPage;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

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

    private function about(): RequiredPage
    {
        return $this->vpp->requiredPages()->where('kind', PageKind::About)->firstOrFail();
    }

    public function test_an_editor_builds_the_about_page_with_stats_milestones_values_gallery_and_clients(): void
    {
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());

        Livewire::test(EditRequiredPage::class, ['record' => $this->about()->getRouteKey()])
            ->assertFormFieldExists('extras.milestones')
            ->fillForm([
                'title' => 'Về D&T',
                'content' => '<p>D&amp;T cung cấp văn phòng phẩm cho nhà máy FDI.</p>',
                'is_published' => true,
                'extras' => [
                    'hero_image' => [UploadedFile::fake()->image('kho.jpg', 1200, 800)],
                    'stats' => [['value' => '10+', 'label' => 'năm kinh nghiệm'], ['value' => '500+', 'label' => 'doanh nghiệp tin dùng']],
                    'milestones' => [
                        ['year' => '2015', 'title' => 'Thành lập công ty', 'description' => 'Bắt đầu cung cấp PPE tại TP.HCM.'],
                        ['year' => '2020', 'title' => 'Mở kho Bình Dương', 'description' => 'Phục vụ các KCN phía Nam.'],
                    ],
                    'values' => [['title' => 'Đúng chuẩn', 'description' => 'Sản phẩm rõ nguồn gốc, đúng tiêu chuẩn.']],
                    'gallery' => [UploadedFile::fake()->image('a.jpg', 800, 600), UploadedFile::fake()->image('b.jpg', 800, 600)],
                    'clients' => ['Samsung', 'Intel'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $html = (string) $this->get('https://vpptrangthietbi.com.vn/gioi-thieu')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<img src="/storage/page-images/[^"]+" alt="Về D&amp;T" width="1200" height="800" fetchpriority="high"#', $html);
        $this->assertMatchesRegularExpression('#10\+.*năm kinh nghiệm.*500\+.*doanh nghiệp tin dùng#s', $html);
        $this->assertMatchesRegularExpression('#2015.*Thành lập công ty.*Bắt đầu cung cấp PPE.*2020.*Mở kho Bình Dương#s', $html);
        $this->assertStringContainsString('Đúng chuẩn', $html);
        $this->assertSame(2, preg_match_all('#<img src="/storage/page-images/[^"]+" alt="Hình ảnh hoạt động – Về D&amp;T" width="800" height="600" loading="lazy"#', $html));
        $this->assertMatchesRegularExpression('#Samsung.*Intel#s', $html);
        $this->assertStringNotContainsString('class="quote-form"', $html);
        $this->assertStringContainsString('href="/yeu-cau-bao-gia?tu=/gioi-thieu"', $html, 'Cuối trang giới thiệu có lời mời báo giá.');
    }

    public function test_a_plain_about_page_still_reads_well_without_the_extra_blocks(): void
    {
        $this->about()->update(['is_published' => true]);

        $this->get('https://vpptrangthietbi.com.vn/gioi-thieu')
            ->assertOk()
            ->assertDontSee('class="about-timeline"', escape: false)
            ->assertDontSee('class="about-stats"', escape: false);
    }

    public function test_extra_blocks_are_only_offered_on_the_about_page(): void
    {
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());
        $privacy = $this->vpp->requiredPages()->where('kind', PageKind::Privacy)->firstOrFail();

        Livewire::test(EditRequiredPage::class, ['record' => $privacy->getRouteKey()])
            ->assertFormFieldHidden('extras.milestones');
    }
}
