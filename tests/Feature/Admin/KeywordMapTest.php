<?php

namespace Tests\Feature\Admin;

use App\Enums\KeywordIntent;
use App\Filament\Resources\KeywordMaps\Pages\CreateKeywordMap;
use App\Filament\Resources\KeywordMaps\Pages\EditKeywordMap;
use App\Models\KeywordMap;
use App\Models\Scopes\SiteScope;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class KeywordMapTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    private Site $ppe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        $this->ppe = Site::factory()->create(['domain' => 'ppetrangthietbi.vn']);
    }

    private function as(string $role, Site $site): void
    {
        $this->workOn($site, User::factory()->{$role}()->hasAttached($site)->create());
    }

    /** @param  array<string, mixed>  $extra */
    private function assign(string $keyword, string $targetUrl, array $extra = []): Testable
    {
        return Livewire::test(CreateKeywordMap::class)
            ->fillForm(['keyword' => $keyword, 'target_url' => $targetUrl, 'intent' => KeywordIntent::Informational, ...$extra])
            ->call('create');
    }

    /** @return list<array{string, string, string}> [từ khoá, domain, đường dẫn] trên toàn hệ thống */
    private function allAssignments(): array
    {
        return KeywordMap::withoutGlobalScope(SiteScope::class)->with('site')->orderBy('keyword')->get()
            ->map(fn (KeywordMap $row) => [$row->keyword, $row->site->domain, $row->target_url])->all();
    }

    public function test_an_editor_assigns_a_keyword_to_one_page_of_the_site(): void
    {
        $this->as('editor', $this->vpp);

        $this->assign('  Giấy in  A4 ', '/kien-thuc/cach-chon-giay-in-a4')->assertHasNoFormErrors();

        $this->assertSame([['giấy in a4', 'vpptrangthietbi.com.vn', '/kien-thuc/cach-chon-giay-in-a4']], $this->allAssignments());
    }

    public function test_a_keyword_already_owned_by_a_page_on_another_site_is_flagged_and_not_taken(): void
    {
        $this->as('editor', $this->ppe);
        $this->assign('găng tay chống cắt', '/kien-thuc/chon-gang-tay-chong-cat');

        $this->as('editor', $this->vpp);
        $this->assign('Găng tay CHỐNG CẮT', '/kien-thuc/vat-tu-nha-may')
            ->assertHasFormErrors(['keyword'])
            ->assertSee('https://ppetrangthietbi.vn/kien-thuc/chon-gang-tay-chong-cat');

        $this->assertSame([['găng tay chống cắt', 'ppetrangthietbi.vn', '/kien-thuc/chon-gang-tay-chong-cat']], $this->allAssignments());
    }

    public function test_a_keyword_already_owned_by_another_page_of_the_same_site_is_flagged(): void
    {
        $this->as('admin', $this->vpp);
        $this->assign('giấy in a4', '/kien-thuc/cach-chon-giay-in-a4');

        $this->assign('giấy in a4', '/danh-muc/giay-van-phong')
            ->assertHasFormErrors(['keyword'])
            ->assertSee('https://vpptrangthietbi.com.vn/kien-thuc/cach-chon-giay-in-a4');
    }

    public function test_only_an_admin_can_override_and_move_the_keyword_to_the_new_page(): void
    {
        $this->as('editor', $this->ppe);
        $this->assign('găng tay chống cắt', '/kien-thuc/chon-gang-tay-chong-cat');

        $this->as('editor', $this->vpp);
        Livewire::test(CreateKeywordMap::class)->assertFormFieldHidden('override');

        $this->as('admin', $this->vpp);
        $this->assign('găng tay chống cắt', '/kien-thuc/vat-tu-nha-may', ['override' => true])
            ->assertHasNoFormErrors();

        $this->assertSame([['găng tay chống cắt', 'vpptrangthietbi.com.vn', '/kien-thuc/vat-tu-nha-may']], $this->allAssignments());
    }

    public function test_the_target_must_be_a_canonical_path_on_the_site(): void
    {
        $this->as('editor', $this->vpp);

        $this->assign('giấy in a4', 'https://vpptrangthietbi.com.vn/kien-thuc/x')->assertHasFormErrors(['target_url']);
        $this->assign('giấy in a4', '/Kien-Thuc/X/')->assertHasFormErrors(['target_url']);
    }

    public function test_writers_do_not_manage_the_keyword_map(): void
    {
        $writer = User::factory()->writer()->hasAttached($this->vpp)->create();

        $this->actingAs($writer)->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/keyword-maps')->assertForbidden();
    }

    public function test_an_editor_cannot_move_or_free_a_keyword_by_editing_or_deleting_it(): void
    {
        $this->as('editor', $this->vpp);
        $this->assign('giấy in a4', '/kien-thuc/cach-chon-giay-in-a4');
        $row = KeywordMap::query()->firstOrFail();

        Livewire::test(EditKeywordMap::class, ['record' => $row->getRouteKey()])
            ->assertFormFieldDisabled('target_url')
            ->assertActionHidden('delete');

        $this->as('admin', $this->vpp);
        Livewire::test(EditKeywordMap::class, ['record' => $row->getRouteKey()])
            ->assertFormFieldEnabled('target_url')
            ->fillForm(['target_url' => '/danh-muc/giay-van-phong'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([['giấy in a4', 'vpptrangthietbi.com.vn', '/danh-muc/giay-van-phong']], $this->allAssignments());
    }

    public function test_the_same_keyword_typed_with_decomposed_vietnamese_accents_is_the_same_keyword(): void
    {
        $this->as('editor', $this->vpp);
        $this->assign('găng tay', '/kien-thuc/gang-tay');

        $decomposed = \Normalizer::normalize('găng tay', \Normalizer::FORM_D);
        $this->assertNotSame('găng tay', $decomposed);
        $this->assign($decomposed, '/kien-thuc/khac')->assertHasFormErrors(['keyword']);
    }
}
