<?php

namespace App\Models;

use App\Enums\Crm\ActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lịch sử chăm sóc: cuộc gọi, email, Zalo, gặp mặt, ghi chú. */
class Activity extends Model
{
    protected $fillable = ['customer_id', 'lead_id', 'quote_id', 'order_id', 'user_id', 'type', 'content', 'happened_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => ActivityType::class, 'happened_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Activity $activity): void {
            $activity->happened_at ??= now();
            $activity->user_id ??= auth()->id();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
