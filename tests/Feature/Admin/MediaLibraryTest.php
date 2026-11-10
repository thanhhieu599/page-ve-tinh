<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\Posts\Actions\InsertMediaAction;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\Media;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use App\Support\MediaLibrary;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    public function test_upload_without_alt_text_is_rejected(): void
    {
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        Livewire::test(CreateMedia::class)
            ->fillForm(['file' => UploadedFile::fake()->image('giay-a4.jpg', 1200, 800)])
            ->call('create')
            ->assertHasFormErrors(['alt' => 'required']);
    }

    public function test_upload_with_alt_text_is_stored_in_webp_sizes_and_listed_for_its_site_only(): void
    {
        $writer = User::factory()->writer()->hasAttached($this->vpp)->create();
        Site::factory()->create(['domain' => 'ppe.example.vn']);
        $this->workOn($this->vpp, $writer);

        Livewire::test(CreateMedia::class)
            ->fillForm([
                'file' => UploadedFile::fake()->image('giay-a4.jpg', 2000, 1000),
                'alt' => 'Ram giấy A4 Double A 80gsm',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $files = Storage::disk('public')->allFiles('media');
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertStringEndsWith('.webp', $file);
        }

        $this->actingAs($writer)
            ->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/media')
            ->assertOk()->assertSee('Ram giấy A4 Double A 80gsm');

        $this->actingAs(User::factory()->admin()->create())
            ->get('https://admin.trangthietbi.test/admin/ppe.example.vn/media')
            ->assertOk()->assertDontSee('Ram giấy A4 Double A 80gsm');
    }

    public function test_svg_upload_is_rejected(): void
    {
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        Livewire::test(CreateMedia::class)
            ->fillForm(['file' => UploadedFile::fake()->create('logo.svg', 2, 'image/svg+xml'), 'alt' => 'Logo'])
            ->call('create')
            ->assertHasFormErrors(['file']);
    }

    private const SELECTION = ['editorSelection' => ['type' => 'text', 'anchor' => 1, 'head' => 1]];

    /** @param  callable(array<string, mixed>): void  $check */
    private function assertImageInserted(Testable $component, callable $check): void
    {
        $component->assertDispatched('run-rich-editor-commands', function (string $event, array $params) use ($check): bool {
            $command = $params['commands'][0] ?? null;
            if (($command['name'] ?? null) !== 'insertContent') {
                return false;
            }
            $node = $command['arguments'][0];
            $check($node['attrs']);

            return $node['type'] === 'image';
        });
    }

    public function test_writer_inserts_an_image_from_the_library_into_a_post(): void
    {
        $file = UploadedFile::fake()->image('but.jpg', 1200, 800);
        $media = MediaLibrary::store($file->getRealPath(), 'Bút ký Parker khắc tên', $this->vpp->id);
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        $component = Livewire::test(CreatePost::class)
            ->callFormComponentAction('content', 'insertMedia', data: ['media_id' => $media->id], arguments: self::SELECTION)
            ->assertHasNoFormComponentActionErrors();

        $this->assertImageInserted($component, function (array $attrs) use ($media): void {
            $this->assertSame($media->url(), $attrs['src']);
            $this->assertSame('Bút ký Parker khắc tên', $attrs['alt']);
        });
    }

    public function test_writer_uploads_a_new_image_with_alt_while_writing(): void
    {
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        $component = Livewire::test(CreatePost::class)
            ->callFormComponentAction('content', 'insertMedia', data: [
                'upload' => UploadedFile::fake()->image('ke.jpg', 900, 600),
                'alt' => 'Kệ tài liệu 5 tầng',
            ], arguments: self::SELECTION)
            ->assertHasNoFormComponentActionErrors();

        $media = Media::query()->where('alt', 'Kệ tài liệu 5 tầng')->firstOrFail();
        $this->assertSame($this->vpp->id, $media->site_id);
        $this->assertImageInserted($component, fn (array $attrs) => $this->assertSame($media->url(), $attrs['src']));
    }

    public function test_new_image_uploaded_while_writing_still_needs_alt(): void
    {
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        Livewire::test(CreatePost::class)
            ->callFormComponentAction('content', 'insertMedia', data: ['upload' => UploadedFile::fake()->image('ke.jpg', 900, 600)], arguments: self::SELECTION)
            ->assertHasFormComponentActionErrors(['alt']);
    }

    public function test_image_of_another_site_cannot_be_picked(): void
    {
        $ppe = Site::factory()->create(['domain' => 'ppe.example.vn']);
        $file = UploadedFile::fake()->image('a.jpg', 800, 600);
        $foreign = MediaLibrary::store($file->getRealPath(), 'Ảnh site khác', $ppe->id);
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        Livewire::test(CreatePost::class)
            ->callFormComponentAction('content', 'insertMedia', data: ['media_id' => $foreign->id], arguments: self::SELECTION)
            ->assertHasFormComponentActionErrors(['media_id']);
    }

    public function test_saving_a_post_with_an_image_from_outside_the_library_is_refused(): void
    {
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'Bài có ảnh ngoài', 'content' => '<p>x</p><p><img src="https://example.com/a.jpg" alt="a"></p>'])
            ->call('create')
            ->assertHasFormErrors(['content']);
    }

    public function test_older_images_can_be_found_by_searching_their_alt(): void
    {
        $file = UploadedFile::fake()->image('old.jpg', 800, 600);
        $old = MediaLibrary::store($file->getRealPath(), 'Găng tay len cũ', $this->vpp->id);
        Media::query()->whereKey($old->id)->update(['created_at' => now()->subYear()]);
        foreach (range(1, 3) as $i) {
            $f = UploadedFile::fake()->image("n{$i}.jpg", 100, 100);
            MediaLibrary::store($f->getRealPath(), "Ảnh mới {$i}", $this->vpp->id);
        }
        $this->workOn($this->vpp, User::factory()->writer()->hasAttached($this->vpp)->create());

        $this->assertSame([$old->id => 'Găng tay len cũ'], InsertMediaAction::search('găng tay', limit: 2));
    }

    public function test_image_used_in_a_post_cannot_be_deleted(): void
    {
        $file = UploadedFile::fake()->image('a.jpg', 800, 600);
        $used = MediaLibrary::store($file->getRealPath(), 'Đang dùng', $this->vpp->id);
        $file2 = UploadedFile::fake()->image('b.jpg', 800, 600);
        $unused = MediaLibrary::store($file2->getRealPath(), 'Không dùng', $this->vpp->id);
        Post::factory()->for($this->vpp)->create(['content' => '<p><img src="'.$used->url().'" alt=""></p>']);
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());

        Livewire::test(ListMedia::class)
            ->assertTableActionHidden(DeleteAction::class, $used)
            ->assertTableActionVisible(DeleteAction::class, $unused);
    }

    public function test_storing_without_a_site_writes_no_files(): void
    {
        $file = UploadedFile::fake()->image('a.jpg', 800, 600);

        try {
            MediaLibrary::store($file->getRealPath(), 'Ảnh', 0);
            $this->fail('Phải báo lỗi khi không có site.');
        } catch (\InvalidArgumentException) {
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }
}
