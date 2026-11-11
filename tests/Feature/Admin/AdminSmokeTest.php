<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\RequiredPage;
use App\Models\Site;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Mọi màn hình quản trị mở được trên MỌI site (danh sách, thêm mới, sửa), giao diện sáng,
 * màu đỏ D&T, không tải ảnh đại diện từ dịch vụ bên ngoài.
 */
class AdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** Mỗi site chạy riêng (ứng dụng mới) như request thật; site kia vẫn tồn tại để kiểm tra không lẫn dữ liệu. */
    public static function sites(): array
    {
        return ['site văn phòng phẩm' => ['vpptrangthietbi.com.vn'], 'site PPE' => ['ppetrangthietbi.vn']];
    }

    #[DataProvider('sites')]
    public function test_every_admin_screen_opens_on_every_site(string $domain): void
    {
        $admin = User::factory()->admin()->create();
        $others = Site::factory()->create(['domain' => $domain === 'ppetrangthietbi.vn' ? 'vpptrangthietbi.com.vn' : 'ppetrangthietbi.vn']);
        Post::factory()->for($others)->create();
        $sites = [Site::factory()->create(['domain' => $domain])];
        $this->actingAs($admin);
        $base = 'https://'.config('sites.admin_domain').'/admin/';

        foreach ($sites as $site) {
            $records = [
                Post::factory()->for($site)->create(),
                Product::factory()->for($site)->create(),
                Category::factory()->for($site)->create(),
                RequiredPage::withoutGlobalScopes()->where('site_id', $site->id)->firstOrFail(),
            ];
            $paths = ['', 'cai-dat-site', 'bao-cao', 'mo-index'];
            foreach (Filament::getPanel('admin')->getResources() as $resource) {
                $slug = $resource::getSlug();
                $paths[] = $slug;
                if ($resource::hasPage('create')) {
                    $paths[] = $slug.'/create';
                }
            }
            foreach ($records as $record) {
                $resourceFor = Filament::getPanel('admin')->getModelResource($record);
                $paths[] = $resourceFor::getSlug().'/'.$record->getKey().'/edit';
            }

            foreach (array_unique($paths) as $path) {
                $response = $this->get($base.$site->domain.($path === '' ? '' : '/'.$path));
                $this->assertContains($response->getStatusCode(), [200, 403], "{$site->domain}/{$path} trả về {$response->getStatusCode()}");
                if ($response->getStatusCode() === 200) {
                    $html = (string) $response->getContent();
                    $this->assertStringNotContainsString('ui-avatars.com', $html, "{$path}: không gọi dịch vụ ảnh đại diện bên ngoài");
                }
            }
        }

        $html = (string) $this->get($base.'vpptrangthietbi.com.vn')->getContent();
        $this->assertStringNotContainsString('theme-switcher', $html, 'Chỉ giao diện sáng.');
    }
}
