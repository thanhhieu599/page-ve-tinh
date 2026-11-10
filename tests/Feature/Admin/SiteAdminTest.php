<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Sites\Pages\CreateSite;
use App\Filament\Resources\Sites\Pages\EditSite;
use App\Models\CompanyProfile;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class SiteAdminTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    private string $themes;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        CompanyProfile::stored()->fill(['legal_name' => 'Công ty TNHH TMDV Phát triển Kỹ thuật D & T', 'name' => 'D&T', 'url' => 'https://trangthietbi.vn'])->save();
        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn', 'name' => 'Văn phòng phẩm D&T']);

        // Thư mục theme riêng cho test: theme vpp thật + một theme mới "congcu" chỉ là thư mục,
        // để chứng minh thêm site + theme không cần sửa code lõi.
        $this->themes = storage_path('framework/testing/themes-'.uniqid());
        File::copyDirectory(resource_path('themes/vpp'), $this->themes.'/vpp');
        File::copyDirectory(resource_path('themes/vpp'), $this->themes.'/congcu');
        File::put($this->themes.'/congcu/partials/footer.blade.php',
            '<footer class="site-footer"><p data-theme="congcu">{{ $site->name }} thuộc {{ \App\Models\CompanyProfile::current()->value(\'legal_name\') }}</p></footer>');
        config(['sites.themes_path' => $this->themes]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themes);

        parent::tearDown();
    }

    private function asAdmin(): void
    {
        $this->workOn($this->vpp, User::factory()->admin()->create());
    }

    public function test_admin_adds_a_new_site_with_a_new_theme_and_it_runs_with_its_own_settings(): void
    {
        $this->asAdmin();

        Livewire::test(CreateSite::class)
            ->assertFormFieldExists('theme')
            ->fillForm([
                'domain' => 'dungcutrangthietbi.vn',
                'name' => 'Dụng cụ D&T',
                'theme' => 'congcu',
                'is_active' => true,
                'settings.logo' => UploadedFile::fake()->image('logo.png', 400, 120),
                'settings.primary_color' => '#0b5394',
                'settings.ga4_id' => 'G-TOOLS12345',
                'settings.gsc_verification' => 'abcDEF123_-xyz789',
                'settings.mautic_campaign_id' => 12,
                'settings.industry_tag' => 'dung-cu',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $site = Site::query()->where('domain', 'dungcutrangthietbi.vn')->firstOrFail();
        $this->assertSame(12, $site->mauticCampaignId());
        $this->assertSame('dung-cu', $site->industryTag());
        $this->assertSame(3, Page::withoutGlobalScopes()->where('site_id', $site->id)->where('is_published', false)->count());

        $html = (string) $this->get('https://dungcutrangthietbi.vn/yeu-cau-bao-gia')->assertOk()->getContent();
        $this->assertStringContainsString('data-theme="congcu"', $html);
        $this->assertStringContainsString('gtag/js?id=G-TOOLS12345', $html);
        $this->assertStringContainsString('<meta name="google-site-verification" content="abcDEF123_-xyz789">', $html);
        $this->assertStringContainsString('--brand-color: #0b5394', $html);
        $this->assertMatchesRegularExpression('#<img src="/storage/site-logos/[^"]+" alt="Dụng cụ D&amp;T"#', $html);
        $this->assertStringContainsString('Công ty TNHH TMDV Phát triển Kỹ thuật D &amp; T', $html);
    }

    public function test_two_sites_keep_their_own_settings_but_share_the_company_details(): void
    {
        $this->vpp->update(['settings' => ['ga4_id' => 'G-VPP111', 'primary_color' => '#c00000']]);
        Site::factory()->create(['domain' => 'ppetrangthietbi.vn', 'name' => 'PPE D&T', 'settings' => ['ga4_id' => 'G-PPE222', 'primary_color' => '#ff9900']]);

        $vpp = (string) $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')->getContent();
        $ppe = (string) $this->get('https://ppetrangthietbi.vn/yeu-cau-bao-gia')->getContent();

        $this->assertStringContainsString('G-VPP111', $vpp);
        $this->assertStringNotContainsString('G-PPE222', $vpp);
        $this->assertStringContainsString('G-PPE222', $ppe);
        $this->assertStringContainsString('--brand-color: #c00000', $vpp);
        $this->assertStringContainsString('--brand-color: #ff9900', $ppe);
        foreach ([$vpp, $ppe] as $html) {
            $this->assertStringContainsString('Công ty TNHH TMDV Phát triển Kỹ thuật D &amp; T', $html);
        }
    }

    public function test_site_settings_are_validated(): void
    {
        $this->asAdmin();

        Livewire::test(CreateSite::class)
            ->fillForm([
                'domain' => 'https://www.Ppe.vn/',
                'name' => 'PPE',
                'theme' => 'khong-co-theme-nay',
                'settings.ga4_id' => 'UA-12345-1',
                'settings.primary_color' => 'đỏ',
                'settings.gsc_verification' => '<script>',
                'settings.industry_tag' => 'Văn phòng phẩm',
            ])
            ->call('create')
            ->assertHasFormErrors(['domain', 'theme', 'settings.ga4_id', 'settings.primary_color', 'settings.gsc_verification', 'settings.industry_tag']);

        Livewire::test(CreateSite::class)
            ->fillForm(['domain' => 'vpptrangthietbi.com.vn', 'name' => 'Trùng', 'theme' => 'vpp'])
            ->call('create')
            ->assertHasFormErrors(['domain' => 'unique']);
    }

    public function test_admin_edits_a_site_and_the_index_switch_is_not_on_this_form(): void
    {
        $this->asAdmin();

        Livewire::test(EditSite::class, ['record' => $this->vpp->getRouteKey()])
            ->assertFormFieldDoesNotExist('is_indexable')
            ->fillForm(['name' => 'VPP Trang Thiết Bị'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('VPP Trang Thiết Bị', $this->vpp->fresh()->name);
    }

    public function test_only_admins_manage_sites(): void
    {
        $editor = User::factory()->editor()->hasAttached($this->vpp)->create();

        $this->actingAs($editor)->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/sites')->assertForbidden();
    }
}
