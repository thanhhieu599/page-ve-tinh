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
}
