<?php

namespace Tests\Feature\Routing;

use App\Models\Redirect;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_301_sends_visitor_to_new_path(): void
    {
        $site = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Redirect::factory()->for($site)->create([
            'from_path' => '/kien-thuc/giay-in-cu',
            'to_path' => '/kien-thuc/giay-in-a4',
            'code' => 301,
        ]);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-cu')
            ->assertStatus(301)
            ->assertRedirect('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4');
    }

    public function test_gone_redirect_returns_410(): void
    {
        $site = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Redirect::factory()->for($site)->gone()->create(['from_path' => '/san-pham/may-fax-cu']);

        $this->get('https://vpptrangthietbi.com.vn/san-pham/may-fax-cu')->assertStatus(410);
    }

    public function test_each_match_counts_a_hit(): void
    {
        $site = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        $moved = Redirect::factory()->for($site)->create(['from_path' => '/cu', 'to_path' => '/moi']);
        $gone = Redirect::factory()->for($site)->gone()->create(['from_path' => '/da-go']);

        $this->get('https://vpptrangthietbi.com.vn/cu');
        $this->get('https://vpptrangthietbi.com.vn/cu');
        $this->get('https://vpptrangthietbi.com.vn/da-go');

        $this->assertSame(2, $moved->fresh()->hits);
        $this->assertSame(1, $gone->fresh()->hits);
    }

    public function test_redirect_only_applies_to_its_own_site(): void
    {
        $vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Site::factory()->create(['domain' => 'ppe.example.vn']);
        Redirect::factory()->for($vpp)->create(['from_path' => '/cu', 'to_path' => '/moi']);

        $this->get('https://ppe.example.vn/cu')->assertNotFound();
    }

    public function test_url_variant_of_old_path_goes_to_target_in_one_redirect(): void
    {
        $site = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Redirect::factory()->for($site)->create([
            'from_path' => '/kien-thuc/giay-in-cu',
            'to_path' => '/kien-thuc/giay-in-a4',
        ]);

        $this->get('http://www.vpptrangthietbi.com.vn/Kien-Thuc/Giay-In-Cu.html?utm_source=zalo')
            ->assertStatus(301)
            ->assertRedirect('https://vpptrangthietbi.com.vn/kien-thuc/giay-in-a4?utm_source=zalo');
    }

    public function test_source_path_is_stored_in_canonical_form(): void
    {
        $site = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Redirect::factory()->for($site)->create(['from_path' => 'Kien-Thuc/Bai-Cu.html/', 'to_path' => '/kien-thuc/bai-moi']);

        $this->get('https://vpptrangthietbi.com.vn/kien-thuc/bai-cu')
            ->assertStatus(301)
            ->assertRedirect('https://vpptrangthietbi.com.vn/kien-thuc/bai-moi');
    }
}
