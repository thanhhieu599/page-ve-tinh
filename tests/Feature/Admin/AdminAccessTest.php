<?php

namespace Tests\Feature\Admin;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login_on_admin_domain(): void
    {
        $this->get('https://admin.trangthietbi.test/admin')
            ->assertRedirect('https://admin.trangthietbi.test/admin/login');
    }

    public function test_admin_panel_is_not_served_on_public_site_domains(): void
    {
        Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);

        $this->get('https://vpptrangthietbi.com.vn/admin')->assertNotFound();
    }

    public function test_user_without_a_role_cannot_enter_the_admin_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('https://admin.trangthietbi.test/admin')->assertForbidden();
    }

    public function test_writer_enters_the_site_they_are_assigned_to(): void
    {
        $site = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        $writer = User::factory()->writer()->hasAttached($site)->create();

        $this->actingAs($writer)
            ->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn')
            ->assertOk();
    }

    public function test_writer_cannot_enter_a_site_they_are_not_assigned_to(): void
    {
        $vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Site::factory()->create(['domain' => 'ppe.example.vn']);
        $writer = User::factory()->writer()->hasAttached($vpp)->create();

        $this->actingAs($writer)
            ->get('https://admin.trangthietbi.test/admin/ppe.example.vn')
            ->assertNotFound();
    }

    public function test_admin_can_enter_every_site_without_being_assigned(): void
    {
        Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        Site::factory()->create(['domain' => 'ppe.example.vn']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn')->assertOk();
        $this->actingAs($admin)->get('https://admin.trangthietbi.test/admin/ppe.example.vn')->assertOk();
    }

    public function test_first_admin_account_can_be_created_from_the_command_line(): void
    {
        Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);

        $this->artisan('admin:create', ['email' => 'thuy@trangthietbi.vn', '--name' => 'Ngô Đình Thụy'])
            ->expectsQuestion('Mật khẩu (ít nhất 12 ký tự)', 'mat-khau-du-dai-123')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'thuy@trangthietbi.vn')->firstOrFail();
        $this->actingAs($admin)
            ->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn')
            ->assertOk();
    }
}
