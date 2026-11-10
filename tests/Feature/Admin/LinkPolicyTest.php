<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\RelationManagers\RevisionsRelationManager;
use App\Models\Post;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class LinkPolicyTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Site::factory()->create(['domain' => 'ppetrangthietbi.vn']);
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());
    }

    private function createPost(string $content): Testable
    {
        return Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'Cách chọn giấy in A4', 'content' => $content])
            ->call('create');
    }

    public function test_a_link_to_another_satellite_site_is_refused_and_named(): void
    {
        $this->createPost('<p>Xem thêm <a href="https://www.ppetrangthietbi.vn/kien-thuc/gang-tay">găng tay</a>.</p>')
            ->assertHasFormErrors(['content'])
            ->assertSee('https://www.ppetrangthietbi.vn/kien-thuc/gang-tay');

        $this->assertSame(0, Post::query()->count());
    }

    public function test_links_inside_the_same_site_and_to_outside_sources_are_fine(): void
    {
        $this->createPost('<p><a href="/kien-thuc/giay-a4">Giấy A4</a>, '
            .'<a href="https://vpptrangthietbi.com.vn/danh-muc/giay">danh mục</a>, '
            .'<a href="https://www.iso.org/standard/45.html">ISO</a>.</p>')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Post::query()->count());
    }

    public function test_a_link_to_the_buy_page_of_a_product_sold_on_this_site_is_allowed(): void
    {
        Product::factory()->for($this->vpp)->create(['buy_url' => 'https://trangthietbi.vn/giay-double-a-a4-80gsm']);

        $this->createPost('<p><a href="https://www.trangthietbi.vn/giay-double-a-a4-80gsm/">Mua giấy Double A</a></p>')
            ->assertHasNoFormErrors();
    }

    public function test_other_links_to_the_main_store_are_refused_without_a_purchase_context(): void
    {
        Product::factory()->for($this->vpp)->create(['buy_url' => 'https://trangthietbi.vn/giay-ngung-ban', 'is_active' => false]);
        $ppe = Site::query()->where('domain', 'ppetrangthietbi.vn')->firstOrFail();
        Product::factory()->for($ppe)->create(['buy_url' => 'https://trangthietbi.vn/gang-tay-towa-370']);

        $this->createPost('<p><a href="https://trangthietbi.vn">Trang thiết bị</a>, '
            .'<a href="https://trangthietbi.vn/giay-ngung-ban">giấy cũ</a>, '
            .'<a href="https://trangthietbi.vn/gang-tay-towa-370">găng tay</a></p>')
            ->assertHasFormErrors(['content'])
            ->assertSee('https://trangthietbi.vn/giay-ngung-ban')
            ->assertSee('https://trangthietbi.vn/gang-tay-towa-370');
    }

    public function test_an_editor_can_mark_a_post_as_purchase_context_to_allow_store_links(): void
    {
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Báo giá giấy A4 cho nhà máy',
                'content' => '<p><a href="https://trangthietbi.vn/khuyen-mai-vpp">Xem chương trình báo giá</a></p>',
                'has_purchase_context' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Post::query()->firstOrFail()->has_purchase_context);
    }

    public function test_a_writer_cannot_mark_a_post_as_purchase_context(): void
    {
        Livewire::test(CreatePost::class)
            ->assertFormFieldHidden('has_purchase_context')
            ->fillForm([
                'title' => 'Bài của người viết',
                'content' => '<p><a href="https://trangthietbi.vn/khuyen-mai-vpp">Khuyến mãi</a></p>',
                'has_purchase_context' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['content']);
    }

    public function test_satellite_links_written_in_unusual_forms_are_still_refused(): void
    {
        foreach ([
            '<a href=https://ppetrangthietbi.vn/x>không có nháp</a>',
            '<a href="https://blog.ppetrangthietbi.vn/x">subdomain</a>',
            '<a href="https:\\\\ppetrangthietbi.vn\\x">gạch ngược</a>',
            '<a href="//PPETRANGTHIETBI.VN./x">viết hoa, dấu chấm cuối</a>',
        ] as $link) {
            $this->createPost("<p>{$link}</p>")->assertHasFormErrors(['content']);
        }

        $this->assertSame(0, Post::query()->count());
    }

    public function test_a_writer_can_keep_editing_a_post_an_editor_marked_as_purchase_context(): void
    {
        $post = Post::factory()->for($this->vpp)->create([
            'content' => '<p><a href="https://trangthietbi.vn/khuyen-mai-vpp">Khuyến mãi</a></p>',
            'has_purchase_context' => true,
        ]);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['content' => '<p>Sửa chính tả. <a href="https://trangthietbi.vn/khuyen-mai-vpp">Khuyến mãi</a></p>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertStringContainsString('Sửa chính tả', $post->fresh()->content);
    }

    public function test_an_old_revision_with_a_banned_link_cannot_be_restored(): void
    {
        $editor = User::factory()->editor()->hasAttached($this->vpp)->create();
        $post = Post::factory()->for($this->vpp)->create([
            'content' => '<p>Bản cũ <a href="https://ppetrangthietbi.vn/x">PPE</a></p>',
        ]);
        $post->update(['content' => '<p>Bản sạch.</p>']);
        $this->workOn($this->vpp, $editor);

        Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $post->fresh(), 'pageClass' => EditPost::class])
            ->callTableAction('restore', $post->revisions()->oldest('id')->firstOrFail())
            ->assertNotified('Không khôi phục được');

        $this->assertSame('<p>Bản sạch.</p>', $post->fresh()->content);
    }
}
