<?php

namespace Tests\Feature\Routing;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    public function test_http_redirects_permanently_to_https(): void
    {
        $this->get('http://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4')
            ->assertStatus(301)
            ->assertRedirect('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4');
    }

    public function test_www_redirects_permanently_to_bare_domain(): void
    {
        $this->get('https://www.vpptrangthietbi.com.vn/kien-thuc/giay-in-a4')
            ->assertStatus(301)
            ->assertRedirect('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4');
    }

    public function test_uppercase_path_redirects_to_lowercase(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/Kien-Thuc/Giay-In-A4')
            ->assertStatus(301)
            ->assertRedirect('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4');
    }

    public function test_trailing_slash_is_removed_and_query_string_kept(): void
    {
        // Bộ test HTTP của Laravel tự cắt "/" ở cuối URL, nên kèm query để giữ "/".
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4/?utm_source=google&utm_medium=cpc')
            ->assertStatus(301)
            ->assertRedirect('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4?utm_source=google&utm_medium=cpc');
    }

    public function test_html_suffix_is_removed(): void
    {
        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4.html')
            ->assertStatus(301)
            ->assertRedirect('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4');
    }

    public function test_several_variants_are_fixed_in_a_single_redirect(): void
    {
        $this->get('http://WWW.vpptrangthietbi.com.vn/Kien-Thuc/Giay-In-A4.html')
            ->assertStatus(301)
            ->assertRedirect('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4');
    }

    public function test_homepage_variant_redirects_to_bare_https_homepage(): void
    {
        $this->get('http://www.vpptrangthietbi.com.vn')
            ->assertStatus(301)
            ->assertRedirect('https://vpptrangthietbi.com.vn/');
    }

    public function test_homepage_query_string_is_kept(): void
    {
        $this->get('http://www.vpptrangthietbi.com.vn/?utm_source=google')
            ->assertStatus(301)
            ->assertRedirect('https://vpptrangthietbi.com.vn/?utm_source=google');
    }

    public function test_hosts_that_are_not_sites_are_left_alone(): void
    {
        // Ví dụ health check của server gọi thẳng bằng IP qua http.
        $this->get('http://127.0.0.1/up')->assertOk();
    }

    public function test_only_get_and_head_are_redirected(): void
    {
        $this->call('OPTIONS', 'http://vpptrangthietbi.com.vn/Kien-Thuc/Giay-In-A4')
            ->assertStatus(404);
    }

    public function test_https_reported_by_trusted_proxy_is_not_redirected_again(): void
    {
        config(['sites.trusted_proxies' => ['127.0.0.1']]);

        // Nginx/Cloudflare kết thúc TLS rồi gọi app qua http, báo https qua header.
        $this->get('http://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4', ['X-Forwarded-Proto' => 'https'])
            ->assertNotFound();
    }

    public function test_https_header_from_untrusted_client_is_ignored(): void
    {
        $this->get('http://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4', ['X-Forwarded-Proto' => 'https'])
            ->assertStatus(301)
            ->assertRedirect('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4');
    }
}
