<?php

namespace App\Models;

use App\Enums\Crm\CustomerStatus;
use App\Enums\Crm\OrderStatus;
use App\Enums\Crm\PaymentStatus;
use App\Enums\Crm\QuoteStatus;
use App\Enums\LeadStatus;
use App\Models\Concerns\HasLineItems;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/** Báo giá (cơ hội bán hàng): Nháp → Đã gửi → Đàm phán → Thắng (tạo đơn) / Thua (ghi lý do). */
class Quote extends Model
{
    use HasLineItems;

    protected $fillable = [
        'number', 'customer_id', 'contact_id', 'site_id', 'owner_id', 'lead_id', 'status', 'valid_until',
        'delivery_terms', 'payment_terms', 'lost_reason', 'vat_rate', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'decided_at' => 'datetime',
            'vat_rate' => 'decimal:2',
        ];
    }

    protected static function numberPrefix(): string
    {
        return 'BG';
    }

    protected static function booted(): void
    {
        static::creating(function (Quote $quote): void {
            $company = CompanyProfile::current();
            $quote->number ??= static::nextNumber();
            $quote->status ??= QuoteStatus::Draft;
            $quote->vat_rate ??= $company->quote_vat_rate ?? 10;
            $quote->valid_until ??= now()->addDays((int) ($company->quote_valid_days ?? 15))->toDateString();
            $quote->delivery_terms ??= $company->quote_delivery_terms;
            $quote->payment_terms ??= $company->quote_payment_terms;
        });

        static::saving(function (Quote $quote): void {
            if ($quote->isDirty('status')) {
                if ($quote->status === QuoteStatus::Sent && $quote->sent_at === null) {
                    $quote->sent_at = now();
                }
                if (in_array($quote->status, [QuoteStatus::Won, QuoteStatus::Lost], true)) {
                    $quote->decided_at ??= now();
                }
            }
        });
    }

    /** @return HasMany<QuoteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort')->orderBy('id');
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withoutGlobalScopes();
    }

    /** @return HasOne<Order, $this> */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Negotiating], true);
    }

    /** Chốt báo giá: tạo đơn hàng cùng dòng hàng, lead (nếu có) chuyển "Thắng" với giá trị đơn. */
    public function markWon(): Order
    {
        return DB::transaction(function (): Order {
            $this->forceFill(['status' => QuoteStatus::Won, 'lost_reason' => null])->save();

            $order = $this->order ?? new Order;
            if (! $order->exists) {
                $company = CompanyProfile::current();
                $order->forceFill([
                    'number' => Order::nextNumber(),
                    'customer_id' => $this->customer_id,
                    'contact_id' => $this->contact_id,
                    'site_id' => $this->site_id,
                    'owner_id' => $this->owner_id,
                    'quote_id' => $this->id,
                    'status' => OrderStatus::New,
                    'payment_status' => PaymentStatus::Unpaid,
                    'ordered_at' => now()->toDateString(),
                    'due_date' => now()->addDays((int) ($company->payment_due_days ?? 30))->toDateString(),
                    'vat_rate' => $this->vat_rate,
                    'notes' => $this->notes,
                ])->save();
                foreach ($this->items as $item) {
                    $order->items()->create($item->only(['product_id', 'sku', 'name', 'unit', 'quantity', 'unit_price', 'sort']));
                }
                $order->recalculateTotals();
            }

            if ($this->lead) {
                $this->lead->forceFill(['status' => LeadStatus::Won, 'deal_value' => $order->total, 'won_at' => now()])->save();
            }
            $this->customer->forceFill(['status' => CustomerStatus::Active])->save();

            return $order;
        });
    }

    public function markLost(string $reason): void
    {
        $this->forceFill(['status' => QuoteStatus::Lost, 'lost_reason' => $reason])->save();
        if ($this->lead) {
            $this->lead->forceFill(['status' => LeadStatus::Lost])->save();
        }
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->isSalesManager()) {
            $query->where('owner_id', $user->id);
        }
    }
}
