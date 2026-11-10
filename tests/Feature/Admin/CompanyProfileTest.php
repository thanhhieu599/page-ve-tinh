<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\CompanyProfileSettings;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    private Site $ppe;

    protected function setUp(): void
    {
        parent::setUp();

        config(['company.legal_name' => 'Tên mặc định từ cấu hình', 'company.phone' => null]);
        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn', 'name' => 'VPP D&T']);
        $this->ppe = Site::factory()->create(['domain' => 'ppetrangthietbi.vn', 'name' => 'PPE D&T']);
    }

    public function test_admin_edits_company_details_once_and_every_site_shows_them(): void
    {
        $this->workOn($this->vpp, User::factory()->admin()->create());

        Livewire::test(CompanyProfileSettings::class)
            ->assertFormSet(['legal_name' => 'Tên mặc định từ cấu hình'])
            ->fillForm([
                'legal_name' => 'Công ty TNHH TMDV Phát triển Kỹ thuật D & T',
                'tax_id' => '0312345678',
                'phone' => '028 1234 5678',
                'email' => 'sales@trangthietbi.vn',
                'street_address' => '12 Đường Số 7',
                'locality' => 'Quận Tân Phú',
                'region' => 'TP. Hồ Chí Minh',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        foreach (['vpptrangthietbi.com.vn', 'ppetrangthietbi.vn'] as $domain) {
            $this->get("https://{$domain}/yeu-cau-bao-gia")
                ->assertSee('Công ty TNHH TMDV Phát triển Kỹ thuật D &amp; T', escape: false)
                ->assertSee('"taxID":"0312345678"', escape: false)
                ->assertDontSee('Tên mặc định từ cấu hình');
        }
    }

    public function test_invalid_company_details_are_rejected(): void
    {
        $this->workOn($this->vpp, User::factory()->admin()->create());

        Livewire::test(CompanyProfileSettings::class)
            ->fillForm(['legal_name' => '', 'email' => 'khong-phai-email', 'logo_url' => 'http://trangthietbi.vn/logo.png'])
            ->call('save')
            ->assertHasFormErrors(['legal_name' => 'required', 'email', 'logo_url']);
    }

    public function test_only_admins_edit_company_details(): void
    {
        $editor = User::factory()->editor()->hasAttached($this->vpp)->create();

        $this->actingAs($editor)->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/thong-tin-cong-ty')->assertForbidden();
    }
}
