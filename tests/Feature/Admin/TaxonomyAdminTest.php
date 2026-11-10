<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class TaxonomyAdminTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    private Site $ppe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        $this->ppe = Site::factory()->create(['domain' => 'ppe.example.vn']);
    }

    public function test_writer_fills_categories_tags_author_and_faqs_and_they_appear_on_the_published_post(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['slug' => 'giay', 'name' => 'Giấy văn phòng']);
        $author = Author::factory()->create(['slug' => 'nguyen-van-an', 'name' => 'Nguyễn Văn An']);
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Giấy in A4 cho nhà máy',
                'content' => '<p>Nội dung.</p>',
                'categories' => [$paper->id],
                'author_id' => $author->id,
                'faqs' => [
                    ['question' => 'Nên mua giấy 70gsm hay 80gsm?', 'answer' => 'In nội bộ dùng 70gsm.'],
                    ['question' => 'Một thùng có bao nhiêu ram?', 'answer' => '5 ram.'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Post::query()->where('slug', 'giay-in-a4-cho-nha-may')->firstOrFail()->publish(now()->subMinute());

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4-cho-nha-may')
            ->assertOk()
            ->assertSee('<a href="/danh-muc/giay">Giấy văn phòng</a>', escape: false)
            ->assertSee('Nguyễn Văn An')
            ->assertSeeInOrder(['Nên mua giấy 70gsm hay 80gsm?', 'Một thùng có bao nhiêu ram?']);
    }

    public function test_post_cannot_be_put_in_a_category_of_another_site(): void
    {
        $foreign = Category::factory()->for($this->ppe)->create();
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'Bài thử', 'content' => '<p>x</p>', 'categories' => [$foreign->id]])
            ->call('create')
            ->assertHasFormErrors(['categories.0']);
    }

    public function test_writer_manages_tags_but_not_categories_or_authors(): void
    {
        $writer = User::factory()->writer()->hasAttached($this->vpp)->create();
        $base = 'https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/';

        $this->actingAs($writer)->get($base.'tags')->assertOk();
        $this->actingAs($writer)->get($base.'categories')->assertForbidden();
        $this->actingAs($writer)->get($base.'authors')->assertForbidden();
    }

    public function test_editor_creates_a_child_category_only_under_a_parent_of_the_same_site(): void
    {
        $paper = Category::factory()->for($this->vpp)->create(['slug' => 'giay', 'name' => 'Giấy văn phòng']);
        $foreign = Category::factory()->for($this->ppe)->create();
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Giấy A4', 'parent_id' => $foreign->id])
            ->call('create')
            ->assertHasFormErrors(['parent_id']);

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Giấy A4', 'parent_id' => $paper->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->get('https://vpptrangthietbi.com.vn/danh-muc/giay-a4')
            ->assertOk()
            ->assertSee('<a href="/danh-muc/giay">Giấy văn phòng</a>', escape: false);
    }

    public function test_post_in_an_expert_review_category_needs_an_expert_to_publish(): void
    {
        $steel = Category::factory()->for($this->vpp)->requiresExpertReview()->create();
        $post = Post::factory()->for($this->vpp)->hasAttached($steel)->create();
        $post->submitForReview();

        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->assertActionHidden('publish');

        $this->workOn($this->vpp, User::factory()->editor()->expertReviewer()->hasAttached($this->vpp)->create());
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->assertActionVisible('publish');
    }
}
