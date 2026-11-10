<?php

namespace Tests\Feature\Admin;

use App\Enums\PageKind;
use App\Filament\Pages\SearchIndexing;
use App\Models\Author;
use App\Models\Post;
use App\Models\RequiredPage;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class IndexSwitchTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private const PAGE = 'https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/mo-index';

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    private function user(string $role): User
    {
        return User::factory()->{$role}()->hasAttached($this->vpp)->create();
    }

    private function publishPosts(int $count, ?Author $author = null): void
    {
        Post::factory()->for($this->vpp)->published()->count($count)->create(['author_id' => $author?->id]);
    }

    public function test_admin_sees_which_conditions_are_still_missing(): void
    {
        $this->publishPosts(7, Author::factory()->create(['bio' => 'Chuyên viên EHS 10 năm kinh nghiệm.']));

        $this->actingAs($this->user('admin'))->get(self::PAGE)
            ->assertOk()
            ->assertSeeText('Đang chặn Google (noindex)')
            ->assertSeeText('Ít nhất 20 bài đã duyệt và đã đăng')
            ->assertSeeText('Mới có 7/20 bài')
            ->assertSeeText('Trang giới thiệu công ty')
            ->assertSeeText('Chính sách quyền riêng tư')
            ->assertSeeText('Trang liên hệ')
            ->assertSeeText('Còn ở dạng nháp: biên tập và bật hiển thị trong mục Trang bắt buộc');
    }

    public function test_posts_without_an_author_profile_are_reported(): void
    {
        $this->publishPosts(18, Author::factory()->create(['bio' => 'Chuyên viên EHS 10 năm kinh nghiệm.']));
        $this->publishPosts(1, Author::factory()->create(['bio' => null]));
        $this->publishPosts(1);

        $this->actingAs($this->user('admin'))->get(self::PAGE)
            ->assertSeeText('Đủ 20 bài')
            ->assertSeeText('2 bài chưa có tác giả kèm hồ sơ giới thiệu');
    }

    public function test_the_switch_stays_locked_until_every_condition_is_met(): void
    {
        $this->publishPosts(20, Author::factory()->create(['bio' => 'Chuyên viên EHS 10 năm kinh nghiệm.']));
        $this->workOn($this->vpp, $this->user('admin'));

        Livewire::test(SearchIndexing::class)
            ->assertActionVisible('enableIndexing')
            ->assertActionDisabled('enableIndexing')
            ->callAction('enableIndexing');

        $this->get('https://vpptrangthietbi.com.vn/robots.txt')->assertDontSee('Sitemap:');
    }

    public function test_only_admins_can_flip_the_switch(): void
    {
        $this->workOn($this->vpp, $this->user('editor'));

        Livewire::test(SearchIndexing::class)
            ->assertSuccessful()
            ->assertActionHidden('enableIndexing')
            ->assertActionHidden('disableIndexing');

        $this->actingAs($this->user('writer'))->get(self::PAGE)->assertForbidden();
    }

    public function test_admin_can_take_an_indexed_site_back_out_of_google(): void
    {
        $this->vpp->forceFill(['is_indexable' => true])->save();
        $this->workOn($this->vpp, $this->user('admin'));

        Livewire::test(SearchIndexing::class)
            ->assertActionHidden('enableIndexing')
            ->callAction('disableIndexing');

        $this->get('https://vpptrangthietbi.com.vn/robots.txt')->assertDontSee('Sitemap:');
    }

    private function publishRequiredPages(): void
    {
        RequiredPage::withoutGlobalScopes()->where('site_id', $this->vpp->id)->update(['is_published' => true]);
    }

    public function test_an_admin_turns_indexing_on_once_every_condition_is_met(): void
    {
        $this->publishPosts(20, Author::factory()->create(['bio' => 'Chuyên viên EHS 10 năm kinh nghiệm.']));
        $this->publishRequiredPages();
        $this->workOn($this->vpp, $this->user('admin'));

        Livewire::test(SearchIndexing::class)
            ->assertActionEnabled('enableIndexing')
            ->callAction('enableIndexing');

        $this->get('https://vpptrangthietbi.com.vn/robots.txt')->assertSee('Sitemap: https://vpptrangthietbi.com.vn/sitemap.xml', escape: false);
        $this->get('https://vpptrangthietbi.com.vn/gioi-thieu')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_one_unpublished_required_page_keeps_the_switch_locked(): void
    {
        $this->publishPosts(20, Author::factory()->create(['bio' => 'Chuyên viên EHS 10 năm kinh nghiệm.']));
        $this->publishRequiredPages();
        RequiredPage::withoutGlobalScopes()->where('site_id', $this->vpp->id)->where('kind', PageKind::Privacy)->update(['is_published' => false]);
        $this->workOn($this->vpp, $this->user('admin'));

        Livewire::test(SearchIndexing::class)->assertActionDisabled('enableIndexing');
    }
}
