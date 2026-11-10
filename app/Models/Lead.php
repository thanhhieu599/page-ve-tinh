<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Jobs\SyncLeadToMautic;
use App\Models\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

/**
 * Yêu cầu báo giá (RFQ) từ khách trên site vệ tinh. CMS là bản gốc; Mautic là bản sao.
 */
class Lead extends Model
{
    use BelongsToSite;

    protected $fillable = [
        'name', 'company', 'email', 'phone', 'need', 'product_id',
        'page_url', 'landing_url', 'utm_source', 'utm_medium', 'utm_campaign',
        'status', 'deal_value', 'won_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'consent_at' => 'datetime',
            'won_at' => 'datetime',
            'synced_to_mautic_at' => 'datetime',
            'mautic_contact_id' => 'integer',
            'mautic_synced_steps' => 'array',
            'deal_value' => 'integer',
            'is_verified' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Chuyển sang "Thắng" mà chưa có ngày chốt thì lấy ngày hiện tại.
        static::saving(function (Lead $lead): void {
            if ($lead->status === LeadStatus::Won && $lead->won_at === null) {
                $lead->won_at = now();
            }
        });

        // Mọi lead mới đều được đẩy sang Mautic qua hàng đợi, sau khi đã lưu chắc vào CMS.
        // Hàng đợi lỗi (vd. Redis sập) không được làm hỏng việc khách gửi form: lead đã lưu,
        // chỉ ghi log; lead nằm trong bộ lọc "Chưa đồng bộ" để bấm đồng bộ lại.
        static::created(function (Lead $lead): void {
            try {
                SyncLeadToMautic::dispatch($lead->id)->afterCommit();
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    public function isSyncedToMautic(): bool
    {
        return $this->synced_to_mautic_at !== null;
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
