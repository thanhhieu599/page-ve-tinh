<?php

namespace App\Models;

use App\Enums\Crm\CustomerStatus;
use App\Enums\Crm\CustomerType;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/** Khách hàng doanh nghiệp (nhà máy, công ty) dùng chung cho mọi site. */
class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'tax_code', 'type', 'industry', 'size', 'address', 'industrial_zone', 'province', 'website',
        'email_domain', 'status', 'owner_id', 'first_site_id', 'reorder_cycle_days', 'next_reorder_at', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'type' => CustomerType::class,
            'next_reorder_at' => 'date',
            'reorder_cycle_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            $customer->tax_code = filled($customer->tax_code) ? preg_replace('/\s+/', '', (string) $customer->tax_code) : null;
            $customer->email_domain = filled($customer->email_domain) ? mb_strtolower(trim((string) $customer->email_domain)) : null;
            $customer->status ??= CustomerStatus::Prospect;
        });
    }

    /** @return HasMany<Contact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /** @return HasOne<Contact, $this> */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(Contact::class)->orderByDesc('is_primary')->oldest('id');
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class)->withoutGlobalScopes();
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function firstSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'first_site_id');
    }

    /** Công nợ còn phải thu (đơn chưa huỷ). */
    public function outstanding(): int
    {
        return (int) $this->orders()->where('status', '!=', 'cancelled')->sum(DB::raw('GREATEST(total - paid_amount, 0)'));
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
