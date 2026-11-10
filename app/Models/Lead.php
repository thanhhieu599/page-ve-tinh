<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Models\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
