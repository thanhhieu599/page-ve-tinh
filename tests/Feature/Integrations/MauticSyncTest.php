<?php

namespace Tests\Feature\Integrations;

use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Jobs\SyncLeadToMautic;
use App\Mail\LeadSyncFailed;
use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use App\Support\Mautic\MauticClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\WorksInAdmin;
use Tests\TestCase;

class MauticSyncTest extends TestCase
{
    use RefreshDatabase;
    use WorksInAdmin;

    private Site $vpp;

    private const MAUTIC = 'https://mautic.trangthietbi.vn';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mautic.url' => self::MAUTIC,
            'services.mautic.username' => 'api-user',
            'services.mautic.password' => 'api-pass',
        ]);
        $this->vpp = Site::factory()->create([
            'domain' => 'vpptrangthietbi.com.vn',
            'settings' => ['mautic_campaign_id' => 7, 'industry_tag' => 'van-phong-pham'],
        ]);
    }

    private function fakeMauticOk(): void
    {
        Http::fake([
            self::MAUTIC.'/api/contacts/new' => Http::response(['contact' => ['id' => 4321]], 201),
            self::MAUTIC.'/api/*' => Http::response(['success' => 1], 200),
        ]);
    }

    private function submitQuote(array $overrides = []): void
    {
        $this->get('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia?utm_source=zalo&utm_medium=oa&utm_campaign=vpp-thang-11');
        $this->post('https://vpptrangthietbi.com.vn/yeu-cau-bao-gia', [
            'name' => 'Nguyễn Thị Mai',
            'company' => 'Công ty TNHH Điện tử ABC Việt Nam',
            'email' => 'mai.nguyen@abc-vn.com',
            'phone' => '0912 345 678',
            'need' => 'Cần 200 thùng giấy A4 mỗi tháng.',
            'consent' => '1',
            ...$overrides,
        ])->assertRedirect();
    }

    public function test_new_lead_becomes_a_tagged_mautic_contact_in_the_site_campaign(): void
    {
        $this->fakeMauticOk();

        $this->submitQuote();

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== self::MAUTIC.'/api/contacts/new') {
                return false;
            }
            $this->assertSame('Basic '.base64_encode('api-user:api-pass'), $request->header('Authorization')[0]);
            $this->assertSame('mai.nguyen@abc-vn.com', $request['email']);
            $this->assertSame('Nguyễn Thị', $request['lastname']);
            $this->assertSame('Mai', $request['firstname']);
            $this->assertSame('Công ty TNHH Điện tử ABC Việt Nam', $request['company']);
            $this->assertSame('0912 345 678', $request['phone']);
            $this->assertEqualsCanonicalizing(
                ['rfq', 'site-vpptrangthietbi.com.vn', 'nganh-van-phong-pham', 'chua-xac-minh'],
                $request['tags'],
            );

            return true;
        });
        Http::assertSent(fn (Request $r) => $r->url() === self::MAUTIC.'/api/contacts/4321/utm/add'
            && $r['utm_source'] === 'zalo' && $r['utm_medium'] === 'oa' && $r['utm_campaign'] === 'vpp-thang-11');
        Http::assertSent(fn (Request $r) => $r->url() === self::MAUTIC.'/api/notes/new'
            && $r['lead'] === 4321 && str_contains($r['text'], 'Cần 200 thùng giấy A4 mỗi tháng.'));
        // Chưa qua Turnstile (có thể là bot): vào Mautic có tag, nhưng không vào campaign chăm sóc.
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/api/campaigns/'));

        $this->unsyncedListAsEditor()->assertDontSee('mai.nguyen@abc-vn.com');
    }

    private function unsyncedListAsEditor(): TestResponse
    {
        $editor = User::factory()->editor()->hasAttached($this->vpp)->create();

        return $this->actingAs($editor)
            ->get('https://admin.trangthietbi.test/admin/vpptrangthietbi.com.vn/leads?filters[mautic][value]=0');
    }

    public function test_when_mautic_is_down_the_visitor_still_succeeds_and_the_lead_waits_as_unsynced(): void
    {
        Queue::fake();

        $this->submitQuote();

        Queue::assertPushed(SyncLeadToMautic::class);
        $this->unsyncedListAsEditor()->assertSee('mai.nguyen@abc-vn.com');
    }

    public function test_a_failing_sync_is_retried_and_after_the_last_try_the_developer_is_emailed(): void
    {
        Queue::fake();
        Mail::fake();
        config(['sites.alert_email' => 'dev@trangthietbi.vn']);
        Http::fake([self::MAUTIC.'/*' => Http::response(['error' => 'down'], 503)]);
        $this->submitQuote();
        $job = new SyncLeadToMautic(Lead::query()->firstOrFail()->id);

        try {
            $job->handle(app(MauticClient::class));
            $this->fail('Lỗi Mautic phải ném ngoại lệ để hàng đợi thử lại.');
        } catch (RequestException $exception) {
            $this->assertSame(5, $job->tries);
            $job->failed($exception);
        }

        Mail::assertSent(LeadSyncFailed::class, fn (LeadSyncFailed $mail) => $mail->hasTo('dev@trangthietbi.vn'));
        $this->unsyncedListAsEditor()->assertSee('mai.nguyen@abc-vn.com');
    }

    public function test_without_mautic_settings_nothing_is_sent_and_the_lead_waits(): void
    {
        config(['services.mautic.url' => null]);
        Http::fake();

        $this->submitQuote();

        Http::assertNothingSent();
        $this->unsyncedListAsEditor()->assertSee('mai.nguyen@abc-vn.com');
    }

    public function test_editor_resyncs_a_waiting_lead_once_mautic_is_back(): void
    {
        config(['services.mautic.url' => null]);
        $this->submitQuote();
        $lead = Lead::query()->firstOrFail();

        config(['services.mautic.url' => self::MAUTIC]);
        $this->fakeMauticOk();
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());

        Livewire::test(ListLeads::class)
            ->assertTableActionVisible('resyncMautic', $lead)
            ->callTableAction('resyncMautic', $lead);

        Http::assertSent(fn (Request $r) => $r->url() === self::MAUTIC.'/api/contacts/new');
        $this->unsyncedListAsEditor()->assertDontSee('mai.nguyen@abc-vn.com');
        Livewire::test(ListLeads::class)->assertTableActionHidden('resyncMautic', $lead->fresh());
    }

    public function test_a_synced_lead_is_not_sent_twice(): void
    {
        $this->fakeMauticOk();
        $this->submitQuote();
        $lead = Lead::query()->firstOrFail();

        dispatch_sync(new SyncLeadToMautic($lead->id));

        Http::assertSentCount(3);
    }

    public function test_verified_lead_carries_no_unverified_tag_and_single_word_names_still_work(): void
    {
        config(['sites.turnstile.site_key' => 'k', 'sites.turnstile.secret_key' => 's']);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
            self::MAUTIC.'/api/contacts/new' => Http::response(['contact' => ['id' => 1]], 201),
            self::MAUTIC.'/api/*' => Http::response(['success' => 1]),
        ]);

        $this->submitQuote(['name' => 'Hùng', 'cf-turnstile-response' => 'ok']);

        Http::assertSent(fn (Request $r) => $r->url() === self::MAUTIC.'/api/contacts/new'
            && $r['firstname'] === 'Hùng' && $r['lastname'] === '' && ! in_array('chua-xac-minh', $r['tags'], true));
        Http::assertSent(fn (Request $r) => $r->url() === self::MAUTIC.'/api/campaigns/7/contact/1/add');
    }

    public function test_a_retry_after_partial_success_does_not_duplicate_the_note_or_utm(): void
    {
        Queue::fake();
        $this->submitQuote();
        $lead = Lead::query()->firstOrFail();
        $noteDown = true;
        $sent = [];
        Http::fake(function (Request $request) use (&$noteDown, &$sent) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/api/notes/new' && $noteDown) {
                return Http::response(['error' => 'down'], 503);
            }
            $sent[] = $path;

            return $path === '/api/contacts/new'
                ? Http::response(['contact' => ['id' => 4321]], 201)
                : Http::response(['success' => 1]);
        });

        try {
            (new SyncLeadToMautic($lead->id))->handle(app(MauticClient::class));
            $this->fail('Lỗi ghi chú phải ném ngoại lệ để thử lại.');
        } catch (RequestException) {
        }
        $noteDown = false;
        (new SyncLeadToMautic($lead->id))->handle(app(MauticClient::class));

        $this->assertSame(1, count(array_keys($sent, '/api/contacts/4321/utm/add', true)));
        $this->assertSame(1, count(array_keys($sent, '/api/notes/new', true)));
        $this->unsyncedListAsEditor()->assertDontSee('mai.nguyen@abc-vn.com');
    }

    public function test_the_same_lead_is_not_queued_twice_while_a_sync_is_pending(): void
    {
        Queue::fake();
        $this->submitQuote();
        $lead = Lead::query()->firstOrFail();
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());

        Livewire::test(ListLeads::class)->callTableAction('resyncMautic', $lead);

        Queue::assertPushed(SyncLeadToMautic::class, 1);
    }

    public function test_credentials_are_never_sent_over_plain_http(): void
    {
        Queue::fake();
        config(['services.mautic.url' => 'http://mautic.trangthietbi.vn']);
        Http::fake();
        $this->submitQuote();

        try {
            (new SyncLeadToMautic(Lead::query()->firstOrFail()->id))->handle(app(MauticClient::class));
            $this->fail('MAUTIC_URL không phải https phải báo lỗi.');
        } catch (\RuntimeException) {
        }

        Http::assertNothingSent();
    }

    public function test_the_alert_email_does_not_leak_the_mautic_response_body(): void
    {
        Mail::fake();
        Log::spy();
        config(['sites.alert_email' => 'dev@trangthietbi.vn']);
        Http::fake(['*' => Http::response(['error' => 'contact mai.nguyen@abc-vn.com bị từ chối'], 422)]);
        $exception = new RequestException(Http::get('https://mautic.trangthietbi.vn/api/x'));

        (new SyncLeadToMautic(99))->failed($exception);

        Mail::assertSent(LeadSyncFailed::class, function (LeadSyncFailed $mail): bool {
            $html = $mail->render();

            return str_contains($html, 'HTTP 422') && str_contains($html, '5 lần')
                && ! str_contains($html, 'mai.nguyen@abc-vn.com');
        });
        Log::shouldHaveReceived('error')->once();
    }

    public function test_a_failed_sync_is_logged_even_without_an_alert_email(): void
    {
        Mail::fake();
        Log::spy();
        config(['sites.alert_email' => null]);

        (new SyncLeadToMautic(99))->failed(new \RuntimeException('down'));

        Mail::assertNothingSent();
        Log::shouldHaveReceived('error')->once();
    }

    public function test_resync_warns_instead_of_pretending_when_mautic_is_not_configured(): void
    {
        config(['services.mautic.url' => null]);
        $this->submitQuote();
        $lead = Lead::query()->firstOrFail();
        Queue::fake();
        $this->workOn($this->vpp, User::factory()->editor()->hasAttached($this->vpp)->create());

        Livewire::test(ListLeads::class)
            ->callTableAction('resyncMautic', $lead)
            ->assertNotified('Mautic chưa được cấu hình');

        Queue::assertNothingPushed();
    }
}
