<?php

namespace Tests\Feature\MultiSite;

use App\Models\Site;
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
            ->assertSeeText('127.0.0.1 vpptrangthietbi.com.vn')
            ->assertDontSeeText('Site đang tắt');
    }

    public function test_in_production_an_unknown_host_is_still_not_found(): void
    {
        $this->app['env'] = 'production';

        $this->get('http://127.0.0.1:8000/')->assertNotFound()->assertDontSeeText('vpptrangthietbi.com.vn');
    }
}
