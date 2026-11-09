<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PostAdminTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    private Site $ppe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        $this->ppe = Site::factory()->create(['domain' => 'ppe.example.vn']);
    }

    private function workOn(Site $site, User $user): void
    {
        $this->actingAs($user);
        Filament::setTenant($site);
        Filament::bootCurrentPanel();
    }

    public function test_post_list_shows_only_posts_of_the_selected_site(): void
    {
        $own = Post::factory()->for($this->vpp)->create(['title' => 'Bút ký văn phòng']);
        $other = Post::factory()->for($this->ppe)->create(['title' => 'Găng tay chống cắt']);

        $writer = User::factory()->writer()->hasAttached($this->vpp)->create();

        $this->actingAs($writer)
            ->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/posts')
            ->assertOk()
            ->assertSee('Bút ký văn phòng')
            ->assertDontSee('Găng tay chống cắt');
    }

    public function test_writer_creates_a_draft_with_slug_generated_from_vietnamese_title(): void
    {
        $writer = User::factory()->writer()->hasAttached($this->vpp)->create();
        $this->workOn($this->vpp, $writer);

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Bút bi Thiên Long TL-027 – Đóng gói hộp 20 cây',
                'content' => '<p>Mực xanh, ngòi 0.5mm.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->actingAs($writer)
            ->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/posts')
            ->assertSee('Bút bi Thiên Long TL-027 – Đóng gói hộp 20 cây')
            ->assertSee('but-bi-thien-long-tl-027-dong-goi-hop-20-cay')
            ->assertSee('Nháp');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get('https://admin.trangthietbi.test/admin/ppe.example.vn/posts')
            ->assertDontSee('Bút bi Thiên Long TL-027');
    }

    public function test_duplicate_slug_on_the_same_site_is_rejected(): void
    {
        Post::factory()->for($this->vpp)->create(['slug' => 'giay-in-a4']);
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'Giấy in A4', 'slug' => 'giay-in-a4', 'content' => '<p>Nội dung</p>'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_same_slug_is_allowed_on_another_site(): void
    {
        Post::factory()->for($this->ppe)->create(['slug' => 'huong-dan-chon']);
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'Hướng dẫn chọn', 'slug' => 'huong-dan-chon', 'content' => '<p>Nội dung</p>'])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_slug_with_accents_or_spaces_is_rejected(): void
    {
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'Bìa còng', 'slug' => 'Bìa còng A4', 'content' => '<p>Nội dung</p>'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'regex']);
    }

    public function test_long_path_shows_a_warning_but_still_saves(): void
    {
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());
        $longSlug = 'huong-dan-chon-may-huy-tai-lieu-cho-van-phong-nha-may-fdi-cap-do-bao-mat-p4';

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'Máy huỷ tài liệu', 'slug' => $longSlug, 'content' => '<p>Nội dung</p>'])
            ->assertSee('nên dưới 75')
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_short_path_shows_no_warning(): void
    {
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'Giấy in', 'slug' => 'giay-in-a4'])
            ->assertDontSee('nên dưới 75');
    }

    public function test_dangerous_html_is_removed_before_it_reaches_the_public_page(): void
    {
        Post::factory()->for($this->vpp)->published()->create([
            'slug' => 'bai-co-ma-doc',
            'content' => '<h2>Tiêu chuẩn</h2><p onclick="steal()">An toàn</p>'
                .'<script>alert(1)</script><iframe src="https://evil.example"></iframe>'
                .'<a href="javascript:alert(1)">bấm</a><a href="https://trangthietbi.vn">D&amp;T</a>',
        ]);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/bai-co-ma-doc')
            ->assertOk()
            ->assertSee('<h2>Tiêu chuẩn</h2>', escape: false)
            ->assertSee('<a href="https://trangthietbi.vn">', escape: false)
            ->assertDontSee('<script', escape: false)
            ->assertDontSee('<iframe', escape: false)
            ->assertDontSee('onclick', escape: false)
            ->assertDontSee('javascript:', escape: false);
    }

    public function test_writer_cannot_open_a_post_of_another_site(): void
    {
        $other = Post::factory()->for($this->ppe)->create();
        $writer = User::factory()->writer()->hasAttached($this->vpp)->create();

        $this->actingAs($writer)
            ->get("https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/posts/{$other->id}/edit")
            ->assertNotFound();
    }
}
