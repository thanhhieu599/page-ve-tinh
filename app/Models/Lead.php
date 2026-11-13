<?php

namespace App\Models;

use App\Enums\Crm\LeadChannel;
use App\Enums\LeadStatus;
use App\Jobs\SyncLeadToMautic;
use App\Models\Concerns\BelongsToSite;
use App\Support\Crm\CustomerMatcher;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'status', 'deal_value', 'won_at', 'channel', 'customer_id', 'contact_id', 'assigned_to', 'assigned_at',
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
            'channel' => LeadChannel::class,
            'assigned_at' => 'datetime',
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
            // CRM chung: gắn vào khách hàng đã có (trùng email/SĐT/tên miền công ty), dù khách gửi từ site nào.
            CustomerMatcher::attach($lead);

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

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** @param  Builder<self>  $query  Nhân viên kinh doanh chỉ thấy lead được giao. */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->isSalesManager()) {
            $query->where('assigned_to', $user->id);
        }
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
