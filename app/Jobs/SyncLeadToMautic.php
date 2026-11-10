<?php

namespace App\Jobs;

use App\Mail\LeadSyncFailed;
use App\Models\Lead;
use App\Models\Scopes\SiteScope;
use App\Support\Mautic\MauticClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Đẩy một lead sang Mautic: contact (gộp theo email), tag site/ngành, UTM, ghi chú
 * nhu cầu, campaign của site. CMS là bản gốc; lỗi thì thử lại, quá 5 lần báo email dev.
 */
class SyncLeadToMautic implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $leadId) {}

    /** @return list<int> giãn cách giữa các lần thử lại (giây) */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(MauticClient $mautic): void
    {
        $lead = Lead::withoutGlobalScope(SiteScope::class)->with(['site', 'product'])->find($this->leadId);

        if ($lead === null || $lead->synced_to_mautic_at !== null) {
            return;
        }

        if (! MauticClient::configured()) {
            Log::info('Mautic chưa cấu hình, bỏ qua đồng bộ lead.', ['lead' => $lead->id]);

            return;
        }

        [$firstName, $lastName] = self::splitVietnameseName($lead->name);
        $contactId = $mautic->upsertContact([
            'email' => $lead->email,
            'firstname' => $firstName,
            'lastname' => $lastName,
            'company' => $lead->company,
            'phone' => $lead->phone,
            'tags' => self::tagsFor($lead),
        ]);
        $lead->forceFill(['mautic_contact_id' => $contactId])->save();

        $utm = array_filter([
            'utm_source' => $lead->utm_source,
            'utm_medium' => $lead->utm_medium,
            'utm_campaign' => $lead->utm_campaign,
            'url' => $lead->landing_url,
        ]);
        if ($utm !== []) {
            $mautic->addUtm($contactId, $utm);
        }

        $mautic->addNote($contactId, self::noteFor($lead));

        $campaignId = $lead->site?->settings['mautic_campaign_id'] ?? null;
        if (is_numeric($campaignId)) {
            $mautic->addToCampaign((int) $campaignId, $contactId);
        }

        $lead->forceFill(['synced_to_mautic_at' => now()])->save();
    }

    /** Hết số lần thử: lead giữ trạng thái chưa đồng bộ, báo email cho dev. */
    public function failed(?Throwable $exception): void
    {
        $alertEmail = config('sites.alert_email');

        if (filled($alertEmail)) {
            Mail::to($alertEmail)->send(new LeadSyncFailed($this->leadId, $exception?->getMessage() ?? 'Không rõ lỗi'));
        }
    }

    /** Họ tên Việt: tên là từ cuối, họ và tên đệm là phần còn lại. */
    public static function splitVietnameseName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $first = (string) array_pop($parts);

        return [$first, implode(' ', $parts)];
    }

    /** @return list<string> */
    private static function tagsFor(Lead $lead): array
    {
        $industry = $lead->site?->settings['industry_tag'] ?? null;

        return array_values(array_filter([
            'rfq',
            'site-'.$lead->site?->domain,
            is_string($industry) && $industry !== '' ? 'nganh-'.$industry : null,
            $lead->is_verified ? null : 'chua-xac-minh',
        ]));
    }

    private static function noteFor(Lead $lead): string
    {
        return implode("\n", array_filter([
            'Yêu cầu báo giá từ '.$lead->site?->domain,
            'Nhu cầu: '.$lead->need,
            $lead->product ? "Sản phẩm: {$lead->product->name} ({$lead->product->sku})" : null,
            $lead->page_url ? 'Trang gửi form: '.$lead->page_url : null,
            $lead->landing_url ? 'Trang vào đầu tiên: '.$lead->landing_url : null,
        ]));
    }
}
