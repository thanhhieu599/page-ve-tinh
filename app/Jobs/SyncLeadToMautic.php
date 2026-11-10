<?php

namespace App\Jobs;

use App\Mail\LeadSyncFailed;
use App\Models\Lead;
use App\Models\Scopes\SiteScope;
use App\Support\Mautic\MauticClient;
use App\Support\VietnameseName;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Đẩy một lead sang Mautic: contact (gộp theo email), tag site/ngành, UTM, ghi chú
 * nhu cầu, campaign của site. CMS là bản gốc; lỗi thì thử lại, quá số lần thử báo email dev.
 *
 * Mỗi bước xong được ghi vào lead, nên lần thử lại chỉ làm tiếp bước còn thiếu
 * (không tạo ghi chú, UTM trùng). Mỗi lead chỉ có một job trong hàng đợi.
 */
class SyncLeadToMautic implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** 4 lời gọi × 15 giây, cộng dư; phải nhỏ hơn retry_after (90 giây) của hàng đợi. */
    public int $timeout = 75;

    /** Khoá chống trùng giữ tối đa 2 giờ (đủ cho cả chuỗi thử lại 1 + 5 + 15 + 60 phút). */
    public int $uniqueFor = 7200;

    public function __construct(public int $leadId) {}

    public function uniqueId(): string
    {
        return (string) $this->leadId;
    }

    /** @return list<int> giãn cách giữa các lần thử lại (giây) */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(MauticClient $mautic): void
    {
        $lead = Lead::withoutGlobalScope(SiteScope::class)->with(['site', 'product'])->find($this->leadId);

        if ($lead === null || $lead->isSyncedToMautic()) {
            return;
        }

        if (! MauticClient::configured()) {
            Log::warning('Mautic chưa cấu hình, lead chờ đồng bộ.', ['lead' => $lead->id]);

            return;
        }

        [$firstName, $lastName] = VietnameseName::split($lead->name);
        $contactId = $mautic->upsertContact([
            'email' => $lead->email,
            'firstname' => $firstName,
            'lastname' => $lastName,
            'company' => $lead->company,
            'phone' => $lead->phone,
            'tags' => self::tagsFor($lead),
        ]);
        $lead->forceFill(['mautic_contact_id' => $contactId])->save();

        $this->step($lead, 'utm', function () use ($mautic, $lead, $contactId): void {
            $utm = array_filter([
                'utm_source' => $lead->utm_source,
                'utm_medium' => $lead->utm_medium,
                'utm_campaign' => $lead->utm_campaign,
                'url' => $lead->landing_url,
            ]);
            if ($utm !== []) {
                $mautic->addUtm($contactId, $utm);
            }
        });

        $this->step($lead, 'note', fn () => $mautic->addNote($contactId, self::noteFor($lead)));

        // Lead chưa qua Turnstile có thể là bot: vẫn vào Mautic (tag chua-xac-minh) để sales xem,
        // nhưng không đưa vào campaign chăm sóc tự động.
        $campaignId = $lead->site?->mauticCampaignId();
        if ($campaignId !== null && $lead->is_verified) {
            $this->step($lead, 'campaign', fn () => $mautic->addToCampaign($campaignId, $contactId));
        }

        $lead->forceFill(['synced_to_mautic_at' => now()])->save();
    }

    /** Chạy một bước nếu chưa xong, rồi ghi lại là đã xong. */
    private function step(Lead $lead, string $name, callable $action): void
    {
        $done = $lead->mautic_synced_steps ?? [];

        if (in_array($name, $done, true)) {
            return;
        }

        $action();
        $lead->forceFill(['mautic_synced_steps' => [...$done, $name]])->save();
    }

    /** Hết số lần thử: lead giữ trạng thái chưa đồng bộ, ghi log lỗi và báo email cho dev. */
    public function failed(?Throwable $exception): void
    {
        Log::error('Lead không đồng bộ được sang Mautic sau mọi lần thử.', [
            'lead' => $this->leadId,
            'exception' => $exception,
        ]);

        $alertEmail = config('sites.alert_email');

        if (filled($alertEmail)) {
            Mail::to($alertEmail)->send(new LeadSyncFailed($this->leadId, $this->tries, self::safeReason($exception)));
        }
    }

    /** Tóm tắt lỗi cho email: không kèm nội dung phản hồi Mautic (có thể chứa dữ liệu khách). */
    private static function safeReason(?Throwable $exception): string
    {
        return match (true) {
            $exception instanceof RequestException => 'Mautic trả về HTTP '.$exception->response->status(),
            $exception !== null => class_basename($exception),
            default => 'Không rõ lỗi',
        };
    }

    /** @return list<string> */
    private static function tagsFor(Lead $lead): array
    {
        $industry = $lead->site?->industryTag();

        return array_values(array_filter([
            'rfq',
            'site-'.$lead->site?->domain,
            $industry !== null ? 'nganh-'.$industry : null,
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
