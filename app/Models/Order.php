<?php

namespace App\Models;

use App\Enums\Crm\CustomerStatus;
use App\Enums\Crm\OrderStatus;
use App\Enums\Crm\PaymentStatus;
use App\Models\Concerns\HasLineItems;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Đơn hàng B2B: theo dõi giao hàng, hoá đơn VAT, thanh toán và công nợ (hạn thanh toán).
 * Hoàn tất giao hàng → tính ngày nhắc tái đặt hàng cho khách (vật tư tiêu hao, PPE dùng theo chu kỳ).
 */
class Order extends Model
{
    use HasLineItems;

    protected $fillable = [
        'number', 'customer_id', 'contact_id', 'site_id', 'owner_id', 'quote_id', 'status', 'payment_status',
        'ordered_at', 'delivered_at', 'due_date', 'invoice_number', 'paid_amount', 'vat_rate', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'ordered_at' => 'date',
            'delivered_at' => 'date',
            'due_date' => 'date',
            'paid_amount' => 'integer',
            'vat_rate' => 'decimal:2',
        ];
    }

    protected static function numberPrefix(): string
    {
        return 'DH';
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $order->number ??= static::nextNumber();
            $order->status ??= OrderStatus::New;
            $order->ordered_at ??= now()->toDateString();
            $order->vat_rate ??= CompanyProfile::current()->quote_vat_rate ?? 10;
            $order->due_date ??= $order->ordered_at->copy()->addDays((int) (CompanyProfile::current()->payment_due_days ?? 30))->toDateString();
        });

        static::saving(function (Order $order): void {
            // Tình trạng thanh toán theo số đã thu.
            $paid = (int) $order->paid_amount;
            $order->payment_status = match (true) {
                $order->total > 0 && $paid >= $order->total => PaymentStatus::Paid,
                $paid > 0 => PaymentStatus::Partial,
                default => PaymentStatus::Unpaid,
            };
            if ($order->status === OrderStatus::Completed && $order->delivered_at === null) {
                $order->delivered_at = now()->toDateString();
            }
        });

        static::saved(function (Order $order): void {
            if ($order->wasChanged(['status', 'delivered_at']) && $order->status === OrderStatus::Completed) {
                $customer = $order->customer;
                $cycle = $customer->reorder_cycle_days ?? CompanyProfile::current()->default_reorder_cycle_days;
                $customer->forceFill([
                    'status' => CustomerStatus::Active,
                    'next_reorder_at' => $cycle ? $order->delivered_at->copy()->addDays((int) $cycle)->toDateString() : $customer->next_reorder_at,
                ])->save();
            }
        });
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('sort')->orderBy('id');
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

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function outstanding(): int
    {
        return max(0, (int) $this->total - (int) $this->paid_amount);
    }

    /** @param  Builder<self>  $query  Còn nợ và đã quá hạn thanh toán. */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', '!=', OrderStatus::Cancelled)
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->whereDate('due_date', '<', now()->timezone(config('app.display_timezone'))->toDateString());
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
