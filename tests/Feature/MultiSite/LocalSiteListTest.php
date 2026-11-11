<?php

namespace Tests\Feature\MultiSite;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chạy thử trên máy (APP_ENV=local) mà mở bằng IP/localhost: hiện danh sách site và đường dẫn
 * đúng thay vì 404. Trên máy chủ thật, tên miền lạ vẫn trả 404.
 */
class LocalSiteListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn', 'name' => 'Văn phòng phẩm D&T']);
        Site::factory()->inactive()->create(['domain' => 'da-tat.vn', 'name' => 'Site đang tắt']);
        config(['sites.force_https' => false, 'sites.admin_domain' => 'admin.trangthietbi.test']);
    }

    public function test_on_a_local_machine_opening_the_ip_lists_the_sites_with_the_right_addresses(): void
    {
        $this->app['env'] = 'local';

        $this->get('http://127.0.0.1:8000/')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false)
            ->assertSeeText('Văn phòng phẩm D&T')
            ->assertSee('href="http://vpptrangthietbi.com.vn:8000/"', escape: false)
            ->assertSee('href="http://admin.trangthietbi.test:8000/admin"', escape: false)
            ->assertSeeText('127.0.0.1 vpptrangthietbi.com.vn');
    }

    public function test_switched_off_sites_are_listed_with_how_to_turn_them_on(): void
    {
        $this->app['env'] = 'local';
        $off = Site::query()->where('domain', 'da-tat.vn')->firstOrFail();

        $this->get('http://127.0.0.1:8000/')
            ->assertOk()
            ->assertSeeText('Site đang tắt')
            ->assertSeeText('Đang tắt')
            ->assertSee('href="http://admin.trangthietbi.test:8000/admin/da-tat.vn/sites/'.$off->id.'/edit"', escape: false)
            ->assertSeeText('127.0.0.1 da-tat.vn');

        // Link "bật trong quản trị" mở được thật với tài khoản Admin.
        $this->actingAs(User::factory()->admin()->create())
            ->get('http://admin.trangthietbi.test/admin/da-tat.vn/sites/'.$off->id.'/edit')
            ->assertOk()
            ->assertSeeText('Đang hoạt động');
    }

    public function test_opening_a_switched_off_site_locally_explains_instead_of_404(): void
    {
        $this->app['env'] = 'local';

        $this->get('http://da-tat.vn:8000/')->assertOk()->assertSeeText('Site đang tắt')->assertSeeText('Đang tắt');

        $this->app['env'] = 'production';
        $this->get('http://da-tat.vn/')->assertNotFound();
    }

    public function test_with_no_site_at_all_it_says_how_to_create_one(): void
    {
        $this->app['env'] = 'local';
        Site::query()->delete();

        $this->get('http://127.0.0.1:8000/')
            ->assertOk()
            ->assertSeeText('Chưa có site nào')
            ->assertSeeText('php artisan db:seed --class=Database\\Seeders\\DemoSiteSeeder');
    }

    public function test_in_production_an_unknown_host_is_still_not_found(): void
    {
        $this->app['env'] = 'production';

        $this->get('http://127.0.0.1:8000/')->assertNotFound()->assertDontSeeText('vpptrangthietbi.com.vn');
    }
}
