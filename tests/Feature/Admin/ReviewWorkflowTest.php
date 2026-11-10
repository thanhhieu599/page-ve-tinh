<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Posts\RelationManagers\RevisionsRelationManager;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class ReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    private function writer(): User
    {
        return User::factory()->writer()->hasAttached($this->vpp)->create();
    }

    private function editor(): User
    {
        return User::factory()->editor()->hasAttached($this->vpp)->create();
    }

    public function test_writer_submits_a_draft_for_review_but_cannot_publish_it(): void
    {
        $post = Post::factory()->for($this->vpp)->create(['title' => 'Sổ tay ghi chép A5']);
        Post::factory()->for($this->vpp)->create(['title' => 'Bìa hồ sơ chưa gửi']);
        $this->workOn($this->vpp, $this->writer());

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->assertActionHidden('publish')
            ->callAction('submitForReview')
            ->assertHasNoActionErrors();

        $this->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/posts?tab=pending')
            ->assertSee('Sổ tay ghi chép A5')
            ->assertDontSee('Bìa hồ sơ chưa gửi');
    }

    public function test_editor_publishes_a_pending_post_and_it_goes_live(): void
    {
        $post = Post::factory()->for($this->vpp)->create(['slug' => 'so-tay-a5', 'title' => 'Sổ tay ghi chép A5']);
        $post->submitForReview();
        $this->workOn($this->vpp, $this->editor());

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/so-tay-a5')->assertNotFound();

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction('publish', data: ['published_at' => now()->subMinute()->toDateTimeString()])
            ->assertHasNoActionErrors();

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/so-tay-a5')->assertOk()->assertSee('Sổ tay ghi chép A5');
    }

    public function test_scheduled_post_goes_live_only_at_its_time(): void
    {
        $post = Post::factory()->for($this->vpp)->create(['slug' => 'lich-de-ban-2027']);
        $post->submitForReview();
        $this->workOn($this->vpp, $this->editor());

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction('publish', data: ['published_at' => now()->addDay()->toDateTimeString()])
            ->assertHasNoActionErrors();

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/lich-de-ban-2027')->assertNotFound();

        $this->travel(25)->hours();

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/lich-de-ban-2027')->assertOk();
    }

    public function test_editor_returns_post_to_writer_with_a_note(): void
    {
        $writer = $this->writer();
        $post = Post::factory()->for($this->vpp)->create();
        $post->submitForReview();

        $this->workOn($this->vpp, $this->editor());
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction('returnToDraft', data: ['note' => 'Thiếu thông số định lượng giấy.'])
            ->assertHasNoActionErrors();

        $this->workOn($this->vpp, $writer);
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->assertSee('Thiếu thông số định lượng giấy.')
            ->assertActionVisible('submitForReview');
    }

    public function test_editor_takes_a_published_post_offline(): void
    {
        $post = Post::factory()->for($this->vpp)->published()->create(['slug' => 'bai-sai-thong-tin']);
        $this->workOn($this->vpp, $this->editor());

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction('returnToDraft', data: ['note' => 'Gỡ để sửa giá.'])
            ->assertHasNoActionErrors();

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/bai-sai-thong-tin')->assertNotFound();
    }

    public function test_writer_can_only_edit_drafts(): void
    {
        $writer = $this->writer();
        $draft = Post::factory()->for($this->vpp)->create();
        $pending = Post::factory()->for($this->vpp)->create();
        $pending->submitForReview();
        $published = Post::factory()->for($this->vpp)->published()->create();
        $base = 'https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/posts/';

        $this->actingAs($writer)->get($base.$draft->id.'/edit')->assertOk();
        $this->actingAs($writer)->get($base.$pending->id.'/edit')->assertForbidden();
        $this->actingAs($writer)->get($base.$published->id.'/edit')->assertForbidden();
    }

    public function test_editor_can_edit_published_posts(): void
    {
        $published = Post::factory()->for($this->vpp)->published()->create();

        $this->actingAs($this->editor())
            ->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/posts/'.$published->id.'/edit')
            ->assertOk();
    }

    public function test_every_save_is_kept_in_history_and_an_old_version_can_be_restored(): void
    {
        $editor = $this->editor();
        $post = Post::factory()->for($this->vpp)->published()->create([
            'slug' => 'muc-in-hp-85a',
            'content' => '<p>Bản gốc: hộp mực in được 1.600 trang.</p>',
        ]);
        $this->workOn($this->vpp, $editor);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['content' => '<p>Bản sửa sai: hộp mực in được 16.000 trang.</p>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/muc-in-hp-85a')->assertSee('16.000 trang');

        $original = $post->revisions()->oldest('id')->firstOrFail();

        Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $post->fresh(), 'pageClass' => EditPost::class])
            ->assertCanSeeTableRecords($post->revisions()->get())
            ->assertSee($editor->name)
            ->callTableAction('restore', $original)
            ->assertHasNoTableActionErrors();

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/muc-in-hp-85a')
            ->assertSee('1.600 trang')
            ->assertDontSee('16.000 trang');
    }

    public function test_renaming_a_published_post_redirects_old_urls_straight_to_the_new_one(): void
    {
        $post = Post::factory()->for($this->vpp)->published()->create(['slug' => 'giay-a4-double-a']);
        $this->workOn($this->vpp, $this->editor());

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['slug' => 'giay-in-a4-double-a-70gsm'])->call('save')->assertHasNoFormErrors();
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['slug' => 'giay-in-a4-70gsm'])->call('save')->assertHasNoFormErrors();

        // Cả hai URL cũ đều chuyển thẳng tới URL mới nhất trong một bước.
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-a4-double-a')
            ->assertStatus(301)->assertRedirect('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4-70gsm');
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4-double-a-70gsm')
            ->assertStatus(301)->assertRedirect('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4-70gsm');
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4-70gsm')->assertOk();
    }

    public function test_changing_the_slug_back_serves_the_original_url_again(): void
    {
        $post = Post::factory()->for($this->vpp)->published()->create(['slug' => 'but-bi-tl-027']);
        $this->workOn($this->vpp, $this->editor());

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['slug' => 'but-bi-thien-long-tl-027'])->call('save')->assertHasNoFormErrors();
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['slug' => 'but-bi-tl-027'])->call('save')->assertHasNoFormErrors();

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/but-bi-tl-027')->assertOk();
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/but-bi-thien-long-tl-027')
            ->assertStatus(301)->assertRedirect('https://vpptrangthietbi.com.vn/kien-thuc/but-bi-tl-027');
    }

    public function test_renaming_a_draft_creates_no_redirect(): void
    {
        $post = Post::factory()->for($this->vpp)->create(['slug' => 'ban-nhap-cu']);
        $this->workOn($this->vpp, $this->writer());

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['slug' => 'ban-nhap-moi'])->call('save')->assertHasNoFormErrors();

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/ban-nhap-cu')->assertNotFound();
    }

    public function test_on_an_expert_review_site_only_qualified_reviewers_can_publish(): void
    {
        $ppe = Site::factory()->requiresExpertReview()->create(['domain' => 'ppe.example.vn']);
        $post = Post::factory()->for($ppe)->create(['slug' => 'gang-tay-en-388']);
        $post->submitForReview();

        $plainEditor = User::factory()->editor()->hasAttached($ppe)->create();
        $this->workOn($ppe, $plainEditor);
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->assertActionHidden('publish');

        $expert = User::factory()->editor()->expertReviewer()->hasAttached($ppe)->create();
        $this->workOn($ppe, $expert);
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction('publish', data: ['published_at' => now()->subMinute()->toDateTimeString()])
            ->assertHasNoActionErrors();

        $this->get('https://ppe.example.vn/kien-thuc/gang-tay-en-388')->assertOk();
    }

    public function test_admin_can_always_publish_on_an_expert_review_site(): void
    {
        $ppe = Site::factory()->requiresExpertReview()->create(['domain' => 'ppe.example.vn']);
        $post = Post::factory()->for($ppe)->create();
        $post->submitForReview();
        $this->workOn($ppe, User::factory()->admin()->create());

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->assertActionVisible('publish');
    }

    public function test_plain_editor_still_publishes_on_ordinary_sites(): void
    {
        $post = Post::factory()->for($this->vpp)->create();
        $post->submitForReview();
        $this->workOn($this->vpp, $this->editor());

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->assertActionVisible('publish');
    }

    public function test_deleted_post_goes_offline_and_an_editor_can_restore_it(): void
    {
        $post = Post::factory()->for($this->vpp)->published()->create(['slug' => 'ke-tai-lieu-5-tang']);
        $this->workOn($this->vpp, $this->editor());

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->callAction(DeleteAction::class);
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/ke-tai-lieu-5-tang')->assertNotFound();

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->callAction(RestoreAction::class);
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/ke-tai-lieu-5-tang')->assertOk();
    }

    public function test_writer_bulk_delete_only_removes_drafts_never_published_posts(): void
    {
        $published = Post::factory()->for($this->vpp)->published()->create(['slug' => 'dang-hien-tren-site']);
        $draft = Post::factory()->for($this->vpp)->create(['title' => 'Nháp bỏ đi']);
        $this->workOn($this->vpp, $this->writer());

        Livewire::test(ListPosts::class)->callTableBulkAction(DeleteBulkAction::class, [$published, $draft]);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/dang-hien-tren-site')->assertOk();
        $this->actingAs($this->writer())
            ->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/posts')
            ->assertDontSee('Nháp bỏ đi');
    }

    public function test_publish_time_is_entered_in_vietnam_time(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 01:30:00', 'UTC')); // 08:30 giờ Việt Nam
        $post = Post::factory()->for($this->vpp)->create(['slug' => 'dang-luc-9-gio']);
        $post->submitForReview();
        $this->workOn($this->vpp, $this->editor());

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction('publish', data: ['published_at' => '2026-11-10 09:00:00'])
            ->assertHasNoActionErrors();

        $this->travelTo(Carbon::parse('2026-11-10 01:59:00', 'UTC')); // 08:59 VN
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/dang-luc-9-gio')->assertNotFound();

        $this->travelTo(Carbon::parse('2026-11-10 02:01:00', 'UTC')); // 09:01 VN
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/dang-luc-9-gio')->assertOk();
    }

    public function test_editor_without_expert_right_cannot_change_a_live_expert_post(): void
    {
        $ppe = Site::factory()->requiresExpertReview()->create(['domain' => 'ppe.example.vn']);
        $live = Post::factory()->for($ppe)->published()->create();
        $deleted = Post::factory()->for($ppe)->published()->create(['slug' => 'da-xoa-ppe']);
        $deleted->delete();
        $editor = User::factory()->editor()->hasAttached($ppe)->create();
        $base = 'https://admin.trangthietbi.test/admin/ppe.example.vn/posts/';

        $this->actingAs($editor)->get($base.$live->id.'/edit')->assertForbidden();

        $this->workOn($ppe, $editor);
        Livewire::test(ListPosts::class, ['tableFilters' => ['trashed' => ['value' => 0]]])
            ->callTableBulkAction(RestoreBulkAction::class, [$deleted]);
        $this->get('https://ppe.example.vn/kien-thuc/da-xoa-ppe')->assertNotFound();
    }

    public function test_expert_reviewer_can_still_edit_a_live_expert_post(): void
    {
        $ppe = Site::factory()->requiresExpertReview()->create(['domain' => 'ppe.example.vn']);
        $live = Post::factory()->for($ppe)->published()->create();
        $expert = User::factory()->editor()->expertReviewer()->hasAttached($ppe)->create();

        $this->actingAs($expert)
            ->get('https://admin.trangthietbi.test/admin/ppe.example.vn/posts/'.$live->id.'/edit')
            ->assertOk();
    }

    public function test_renaming_a_scheduled_post_that_never_went_live_creates_no_redirect(): void
    {
        $post = Post::factory()->for($this->vpp)->create(['slug' => 'chua-len-song']);
        $post->publish(now()->addDay());
        $this->workOn($this->vpp, $this->editor());

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['slug' => 'ten-moi-truoc-khi-len-song'])->call('save')->assertHasNoFormErrors();

        $this->travel(2)->days();
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/chua-len-song')->assertNotFound();
    }

    public function test_a_new_post_can_take_over_a_slug_that_used_to_redirect(): void
    {
        $old = Post::factory()->for($this->vpp)->published()->create(['slug' => 'ghim-bam']);
        $this->workOn($this->vpp, $this->editor());
        Livewire::test(EditPost::class, ['record' => $old->getRouteKey()])
            ->fillForm(['slug' => 'ghim-bam-kim-loai'])->call('save')->assertHasNoFormErrors();

        $newcomer = Post::factory()->for($this->vpp)->create(['slug' => 'ghim-bam', 'title' => 'Ghim bấm nhựa màu']);
        $newcomer->publish(now()->subMinute());

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/ghim-bam')->assertOk()->assertSee('Ghim bấm nhựa màu');
    }

    public function test_restoring_a_revision_reloads_the_editor_with_the_restored_text(): void
    {
        $post = Post::factory()->for($this->vpp)->create(['content' => '<p>Bản một.</p>']);
        $this->workOn($this->vpp, $this->editor());
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['content' => '<p>Bản hai.</p>'])->call('save');

        Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $post->fresh(), 'pageClass' => EditPost::class])
            ->callTableAction('restore', $post->revisions()->oldest('id')->firstOrFail())
            ->assertRedirect(PostResource::getUrl('edit', ['record' => $post]));
    }
}
