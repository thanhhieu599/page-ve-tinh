<?php

namespace Tests\Feature\PublicSite;

use App\Models\Post;
use App\Models\Site;
use App\Support\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentImagesTest extends TestCase
{
    use RefreshDatabase;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    public function test_library_image_in_a_post_has_alt_size_srcset_and_lazy_loading(): void
    {
        $file = UploadedFile::fake()->image('giay.jpg', 2000, 1000);
        $media = MediaLibrary::store($file->getRealPath(), 'Ram giấy A4 80gsm', $this->vpp->id);

        Post::factory()->for($this->vpp)->published()->create([
            'slug' => 'giay-a4',
            'content' => '<p>Mở bài.</p><p><img src="'.$media->url().'" alt=""></p>',
        ]);

        $html = $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-a4')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<img[^>]+alt="Ram giấy A4 80gsm"/u', $html);
        $this->assertMatchesRegularExpression('/<img[^>]+width="1600"[^>]+height="800"/', $html);
        $this->assertMatchesRegularExpression('/<img[^>]+srcset="[^"]*480\.webp 480w, [^"]*960\.webp 960w, [^"]*1600\.webp 1600w"/', $html);
        $this->assertMatchesRegularExpression('/<img[^>]+sizes="[^"]+"/', $html);
        $this->assertMatchesRegularExpression('/<img[^>]+loading="lazy"/', $html);
        $this->assertMatchesRegularExpression('/<img[^>]+decoding="async"/', $html);
    }

    public function test_alt_written_in_the_post_is_kept(): void
    {
        $file = UploadedFile::fake()->image('a.jpg', 800, 600);
        $media = MediaLibrary::store($file->getRealPath(), 'Alt thư viện', $this->vpp->id);
        Post::factory()->for($this->vpp)->published()->create([
            'slug' => 'bai',
            'content' => '<p><img src="'.$media->url().'" alt="Alt riêng cho bài"></p>',
        ]);

        $html = $this->get('https://vpptrangthietbi.com.vn/kien-thuc/bai')->getContent();

        $this->assertMatchesRegularExpression('/<img[^>]+alt="Alt riêng cho bài"/u', $html);
        $this->assertMatchesRegularExpression('/<img[^>]+width="800"[^>]+height="600"/', $html);
    }

    public function test_image_of_another_sites_library_gets_no_library_attributes(): void
    {
        $ppe = Site::factory()->create(['domain' => 'ppe.example.vn']);
        $file = UploadedFile::fake()->image('a.jpg', 800, 600);
        $foreign = MediaLibrary::store($file->getRealPath(), 'Ảnh site khác', $ppe->id);
        Post::factory()->for($this->vpp)->published()->create([
            'slug' => 'bai',
            'content' => '<p><img src="'.$foreign->url().'" alt=""></p>',
        ]);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/bai')->assertDontSee('Ảnh site khác');
    }

    public function test_image_urls_are_relative_so_each_site_serves_its_own_images(): void
    {
        config(['filesystems.disks.public.url' => '/storage']);
        Storage::fake('public', ['url' => '/storage']);
        $file = UploadedFile::fake()->image('a.jpg', 800, 600);
        $media = MediaLibrary::store($file->getRealPath(), 'Ảnh', $this->vpp->id);
        Post::factory()->for($this->vpp)->published()->create([
            'slug' => 'bai',
            'content' => '<p><img src="'.$media->url().'" alt=""></p>',
        ]);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/bai')
            ->assertSee('src="/storage/media/'.$this->vpp->id.'/', escape: false)
            ->assertDontSee('src="http', escape: false);
    }
}
