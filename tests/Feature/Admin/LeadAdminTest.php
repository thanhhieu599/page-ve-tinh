<?php

namespace Tests\Feature\Admin;

use App\Enums\LeadStatus;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class LeadAdminTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vpp = Site::factory()->create(['domain' => 'vpptrangthietbi.com.vn']);
    }

    private function lead(): Lead
    {
        $this->post('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia', [
            'name' => 'Trần Văn Hùng', 'company' => 'Samsung Electro-Mechanics Việt Nam',
            'email' => 'hung@sem.vn', 'phone' => '0987654321',
            'need' => '500 thùng giấy A4/tháng', 'consent' => '1',
        ]);

        return Lead::query()->firstOrFail();
    }

    public function test_editor_marks_a_lead_won_with_deal_value_and_the_close_date_is_filled_in(): void
    {
        $lead = $this->lead();
        $this->travelTo(Carbon::parse('2026-11-20 03:00:00', 'UTC'));
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());

        Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->fillForm(['status' => LeadStatus::Won->value, 'deal_value' => 185000000])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->assertSchemaStateSet(['status' => LeadStatus::Won, 'deal_value' => 185000000])
            ->assertSee('Thắng');
        $this->assertSame('2026-11-20 10:00', Lead::query()->firstOrFail()->won_at?->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i'));
    }

    public function test_writer_cannot_see_leads(): void
    {
        $lead = $this->lead();
        $writer = User::factory()->writer()->hasAttached($this->vpp)->create();

        $this->actingAs($writer)->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/leads')->assertForbidden();
        $this->actingAs($writer)->get("https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/leads/{$lead->id}/edit")->assertForbidden();
    }

    public function test_leads_cannot_be_typed_in_by_hand(): void
    {
        $editor = User::factory()->editor()->hasAttached($this->vpp)->create();

        $this->actingAs($editor)->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/leads')
            ->assertOk()->assertDontSee('/leads/create', escape: false);
    }

    public function test_contact_details_and_consent_evidence_are_read_only(): void
    {
        $lead = $this->lead();
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());

        Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->fillForm(['email' => 'doi@khac.vn', 'consent_text' => 'sửa bằng chứng'])
            ->call('save');

        $fresh = Lead::query()->firstOrFail();
        $this->assertSame('hung@sem.vn', $fresh->email);
        $this->assertStringStartsWith('Tôi đồng ý', $fresh->consent_text);
    }
}
