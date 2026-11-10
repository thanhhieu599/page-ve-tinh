<?php

namespace Tests\Feature\Admin;

use App\Enums\PageKind;
use App\Filament\Resources\RequiredPages\Pages\EditRequiredPage;
use App\Models\RequiredPage;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class RequiredPagesAdminTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private const ADMIN = 'https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn';

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Site::factory()->create(['domain' => 'ppetrangthietbi.vn']);
    }

    private function aboutPage(): RequiredPage
    {
        return RequiredPage::withoutGlobalScopes()->where('site_id', $this->vpp->id)->where('kind', PageKind::About)->firstOrFail();
    }

    public function test_an_editor_sees_only_the_three_pages_of_the_site_and_cannot_add_or_remove_pages(): void
    {
        $editor = User::factory()->editor()->hasAttached($this->vpp)->create();

        $this->actingAs($editor)->get(self::ADMIN.'/required-pages')
            ->assertOk()
            ->assertSeeText('Giới thiệu công ty')
            ->assertSeeText('Chính sách quyền riêng tư và bảo vệ dữ liệu cá nhân')
            ->assertSeeText('Liên hệ và yêu cầu báo giá');
        $this->actingAs($editor)->get(self::ADMIN.'/required-pages/create')->assertNotFound();

        $this->workOn($this->vpp, $editor);
        Livewire::test(EditRequiredPage::class, ['record' => $this->aboutPage()->getRouteKey()])->assertActionDoesNotExist('delete');
    }

    public function test_an_editor_completes_the_about_page_and_publishes_it(): void
    {
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());

        Livewire::test(EditRequiredPage::class, ['record' => $this->aboutPage()->getRouteKey()])
            ->fillForm([
                'title' => 'Về Văn phòng phẩm D&T',
                'content' => '<p>Hơn 10 năm cung cấp vật tư cho nhà máy FDI.</p>',
                'is_published' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('https://vpptrangthietbi.com.vn/gioi-thieu')
            ->assertOk()
            ->assertSee('Về Văn phòng phẩm D&amp;T', escape: false)
            ->assertSee('Hơn 10 năm cung cấp vật tư cho nhà máy FDI.');
    }

    public function test_page_content_follows_the_link_policy(): void
    {
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());

        Livewire::test(EditRequiredPage::class, ['record' => $this->aboutPage()->getRouteKey()])
            ->fillForm(['content' => '<p>Xem <a href="https://ppetrangthietbi.vn/gioi-thieu">site PPE</a>, <a href="https://trangthietbi.vn">trang chính</a>.</p>'])
            ->call('save')
            ->assertHasFormErrors(['content'])
            ->assertSee('https://ppetrangthietbi.vn/gioi-thieu')
            ->assertDontSee('chỉ được dùng cho link mua');
    }

    public function test_writers_do_not_edit_the_required_pages(): void
    {
        $writer = User::factory()->writer()->hasAttached($this->vpp)->create();

        $this->actingAs($writer)->get(self::ADMIN.'/required-pages')->assertForbidden();
    }

    public function test_a_page_still_holding_template_placeholders_cannot_be_published(): void
    {
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());

        Livewire::test(EditRequiredPage::class, ['record' => $this->aboutPage()->getRouteKey()])
            ->fillForm(['is_published' => true])
            ->call('save')
            ->assertHasFormErrors(['content']);

        $this->assertFalse($this->aboutPage()->is_published);
        $this->get('https://vpptrangthietbi.com.vn/gioi-thieu')->assertNotFound();
    }
}
