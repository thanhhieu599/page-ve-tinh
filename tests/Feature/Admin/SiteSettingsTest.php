<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\SiteSettings;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

/**
 * "Cài đặt site": mỗi site tự quản lý nhận diện, banner trang chủ, đo lường ngay trong vùng
 * quản trị của site đó. Biên tập sửa giao diện; mã đo lường/tích hợp chỉ Admin.
 */
class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vpp = Site::factory()->create([
            'domain' => 'vpptrangthietbi.com.vn', 'name' => 'Văn phòng phẩm D&T',
            'settings' => ['ga4_id' => 'G-VPP12345', 'mautic_campaign_id' => 7],
        ]);
    }

    private function as(string $role): void
    {
        $this->workOn($this->vpp, User::factory()->{$role}()->hasAttached($this->vpp)->create());
    }

    public function test_an_editor_uploads_home_page_banners_that_show_on_the_site(): void
    {
        $this->as('editor');

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'settings.tagline' => 'Văn phòng phẩm cho nhà máy',
                'settings.banners' => [
                    ['image' => [UploadedFile::fake()->image('khuyen-mai.jpg', 1200, 450)], 'alt' => 'Khuyến mãi giấy A4 tháng 11', 'url' => '/danh-muc/giay'],
                    ['image' => [UploadedFile::fake()->image('deli.jpg', 1200, 450)], 'alt' => 'Dụng cụ văn phòng Deli', 'url' => ''],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $html = (string) $this->get('https://vpptrangthietbi.com.vn/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a href="/danh-muc/giay"[^>]*>\s*<img src="/storage/site-banners/[^"]+" alt="Khuyến mãi giấy A4 tháng 11" width="1200" height="450" fetchpriority="high"#', $html);
        $this->assertMatchesRegularExpression('#<img src="/storage/site-banners/[^"]+" alt="Dụng cụ văn phòng Deli" width="1200" height="450" loading="lazy"#', $html);
        $this->assertStringContainsString('Văn phòng phẩm cho nhà máy', $html);
    }

    public function test_a_banner_can_only_link_inside_the_site(): void
    {
        $this->as('editor');

        Livewire::test(SiteSettings::class)
            ->fillForm(['settings.banners' => [
                ['image' => [UploadedFile::fake()->image('b.jpg', 1200, 450)], 'alt' => 'Banner', 'url' => 'https://ppetrangthietbi.vn/'],
            ]])
            ->call('save')
            ->assertHasFormErrors(['settings.banners.0.url']);
    }

    public function test_an_editor_cannot_change_tracking_codes_and_saving_keeps_them(): void
    {
        $this->as('editor');

        Livewire::test(SiteSettings::class)
            ->assertFormFieldDisabled('settings.ga4_id')
            ->assertFormFieldDisabled('settings.mautic_campaign_id')
            ->fillForm(['settings.tagline' => 'Câu mới'])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = $this->vpp->fresh()->settings;
        $this->assertSame('G-VPP12345', $settings['ga4_id']);
        $this->assertSame(7, (int) $settings['mautic_campaign_id']);
        $this->assertSame('Câu mới', $settings['tagline']);
    }

    public function test_an_admin_changes_tracking_codes_here_too(): void
    {
        $this->as('admin');

        Livewire::test(SiteSettings::class)
            ->assertFormFieldEnabled('settings.ga4_id')
            ->fillForm(['settings.ga4_id' => 'G-NEW98765'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('G-NEW98765', $this->vpp->fresh()->ga4Id());
    }

    public function test_writers_cannot_open_site_settings(): void
    {
        $writer = User::factory()->writer()->hasAttached($this->vpp)->create();

        $this->actingAs($writer)->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/cai-dat-site')->assertNotFound();
    }
}
