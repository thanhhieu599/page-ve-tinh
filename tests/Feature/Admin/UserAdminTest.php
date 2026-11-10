<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_writer_assigned_to_a_site(): void
    {
        $vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        $ppe = Site::factory()->create(['domain' => 'ppe.example.vn']);

        $this->actingAs(User::factory()->admin()->create());
        Filament::setTenant($vpp);
        Filament::bootCurrentPanel();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Nhân viên Marketing',
                'email' => 'marketing@trangthietbi.vn',
                'password' => 'mat-khau-du-dai-123',
                'role' => Role::Writer->value,
                'sites' => [$vpp->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $writer = User::query()->where('email', 'marketing@trangthietbi.vn')->firstOrFail();

        $this->actingAs($writer)->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/posts')->assertOk();
        $this->actingAs($writer)->get('https://admin.trangthietbi.test/admin/ppe.example.vn/posts')->assertNotFound();
        $this->actingAs($writer)->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/users')->assertForbidden();
    }

    public function test_editor_cannot_manage_users(): void
    {
        $vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
        $editor = User::factory()->editor()->hasAttached($vpp)->create();

        $this->actingAs($editor)
            ->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/users')
            ->assertForbidden();
    }

    public function test_admin_grants_expert_review_right_to_an_editor(): void
    {
        $ppe = Site::factory()->requiresExpertReview()->create(['domain' => 'ppe.example.vn']);
        $post = Post::factory()->for($ppe)->create();
        $post->submitForReview();

        $this->actingAs(User::factory()->admin()->create());
        Filament::setTenant($ppe);
        Filament::bootCurrentPanel();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Kỹ sư EHS',
                'email' => 'ehs@trangthietbi.vn',
                'password' => 'mat-khau-du-dai-123',
                'role' => Role::Editor->value,
                'sites' => [$ppe->id],
                'expert_reviewer' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->actingAs(User::query()->where('email', 'ehs@trangthietbi.vn')->firstOrFail());
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->assertActionVisible('publish');
    }
}
