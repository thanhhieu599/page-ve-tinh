<?php

namespace App\Models;

use App\Enums\Crm\TaskType;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Việc cần làm có hạn: gọi lại, gửi báo giá, theo dõi, chăm sóc, nhắc tái đặt hàng, thu nợ. */
class Task extends Model
{
    protected $fillable = ['title', 'type', 'due_at', 'done_at', 'assigned_to', 'created_by', 'customer_id', 'lead_id', 'quote_id', 'order_id', 'notes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => TaskType::class, 'due_at' => 'datetime', 'done_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task): void {
            $task->created_by ??= auth()->id();
            $task->assigned_to ??= auth()->id();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withoutGlobalScopes();
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('done_at');
    }

    /** @param  Builder<self>  $query  Chưa xong, hạn đến hết hôm nay (giờ Việt Nam). */
    #[Scope]
    protected function dueToday(Builder $query): void
    {
        $query->whereNull('done_at')->where('due_at', '<=', now()->timezone(config('app.display_timezone'))->endOfDay()->utc());
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->isSalesManager()) {
            $query->where('assigned_to', $user->id);
        }
    }
}
