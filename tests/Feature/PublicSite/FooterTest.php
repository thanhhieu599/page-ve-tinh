<?php

namespace Tests\Feature\PublicSite;

use App\Enums\PageKind;
use App\Filament\Pages\CompanyProfileSettings;
use App\Models\CompanyProfile;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class FooterTest extends TestCase
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

    private function footer(): string
    {
        $html = (string) $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia')->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<footer\b.*?</footer>#s', $html, $footer));

        return $footer[0];
    }

    public function test_admin_fills_contacts_addresses_socials_and_payments_once_and_the_footer_shows_them(): void
    {
        $this->workOn($this->vpp, User::factory()->admin()->create());

        Livewire::test(CompanyProfileSettings::class)
            ->fillForm([
                'legal_name' => 'Công ty TNHH Thương mại Dịch vụ Phát triển Kỹ thuật D & T',
                'name' => 'D&T',
                'url' => 'https://trangthietbi.vn',
                'email' => 'info@dt-safety.com',
                'phone' => '0933 568 088',
                'street_address' => '212/327/5 Nguyễn Văn Nguyễn',
                'locality' => 'P. Tân Định',
                'region' => 'TP HCM',
                'addresses' => [['label' => 'VPGD', 'address' => '87 Đường 16, KDC Phong Phú 4, Bình Hưng, TP HCM']],
                'contacts' => [['name' => 'Mr Thụy', 'phone' => '0918 826 446'], ['name' => 'Mr Tân', 'phone' => '0934 359 910']],
                'socials' => ['facebook' => 'https://www.facebook.com/dtsafety', 'youtube' => 'https://www.youtube.com/@dtsafety', 'zalo' => 'https://zalo.me/0933568088'],
                'payment_methods' => ['Chuyển khoản ngân hàng', 'Công nợ theo hợp đồng'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $footer = $this->footer();
        foreach (['Liên hệ', 'Danh mục sản phẩm', 'Chính sách', 'Vận chuyển &amp; thanh toán'] as $heading) {
            $this->assertStringContainsString($heading, $footer);
        }
        $this->assertStringContainsString('212/327/5 Nguyễn Văn Nguyễn, P. Tân Định, TP HCM', $footer);
        $this->assertStringContainsString('VPGD: 87 Đường 16, KDC Phong Phú 4, Bình Hưng, TP HCM', $footer);
        $this->assertStringContainsString('Mr Thụy: <a href="tel:0918826446">0918 826 446</a>', $footer);
        $this->assertStringContainsString('href="mailto:info@dt-safety.com"', $footer);
        $this->assertStringContainsString('href="https://www.facebook.com/dtsafety"', $footer);
        $this->assertStringContainsString('href="https://zalo.me/0933568088"', $footer);
        $this->assertStringContainsString('Công nợ theo hợp đồng', $footer);
        $this->assertStringContainsString('© '.now()->year.' Công ty TNHH Thương mại Dịch vụ Phát triển Kỹ thuật D &amp; T', $footer);
        $this->assertStringNotContainsString('Bộ Công Thương', $footer, 'Chưa tải logo BCT thì không hiện.');
    }

    public function test_the_ministry_badge_shows_only_after_admin_uploads_it(): void
    {
        $this->workOn($this->vpp, User::factory()->admin()->create());

        Livewire::test(CompanyProfileSettings::class)
            ->fillForm([
                'legal_name' => 'Công ty D&T', 'name' => 'D&T', 'url' => 'https://trangthietbi.vn',
                'bct_badge' => [UploadedFile::fake()->image('bct.png', 300, 114)],
                'bct_url' => 'http://online.gov.vn/Home/WebDetails/12345',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertMatchesRegularExpression('#<a href="http://online.gov.vn/Home/WebDetails/12345"[^>]*><img src="/storage/company/[^"]+" alt="Đã thông báo Bộ Công Thương" width="150" height="57"#', $this->footer());
    }

    public function test_policy_column_lists_published_policy_pages_in_order(): void
    {
        foreach ([PageKind::Returns, PageKind::Privacy, PageKind::Shipping] as $kind) {
            $this->vpp->requiredPages()->where('kind', $kind)->update(['is_published' => true]);
        }

        $footer = $this->footer();
        $this->assertMatchesRegularExpression('#Chính sách quyền riêng tư.*Chính sách vận chuyển.*Chính sách đổi trả#s', $footer);
        $this->assertStringNotContainsString('href="/hinh-thuc-thanh-toan"', $footer);
    }

    public function test_new_policy_pages_are_drafts_and_not_needed_to_open_indexing(): void
    {
        foreach ([PageKind::Shipping, PageKind::Returns, PageKind::Payment] as $kind) {
            $this->assertFalse($kind->isRequired());
            $this->assertFalse($this->vpp->requiredPages()->where('kind', $kind)->firstOrFail()->is_published);
            $this->get('https://vpptrangthietbi.com.vn'.$kind->path())->assertNotFound();
        }
        $this->assertTrue(PageKind::Privacy->isRequired());
        CompanyProfile::stored(); // tránh cảnh báo không dùng
    }
}
