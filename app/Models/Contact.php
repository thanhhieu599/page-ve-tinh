<?php

namespace App\Models;

use App\Enums\Crm\ContactRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Người liên hệ của khách hàng doanh nghiệp (mua hàng, QA/EHS, kho…). */
class Contact extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'name', 'role', 'title', 'phone', 'email', 'zalo', 'is_primary', 'notes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['role' => ContactRole::class, 'is_primary' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Contact $contact): void {
            $contact->email = filled($contact->email) ? mb_strtolower(trim((string) $contact->email)) : null;
            $contact->phone_digits = self::digits($contact->phone);
        });
    }

    /** Số điện thoại chỉ còn chữ số, bỏ mã 84 đầu (để so trùng). */
    public static function digits(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '' || $digits === null) {
            return null;
        }

        return str_starts_with($digits, '84') && strlen($digits) >= 11 ? '0'.substr($digits, 2) : $digits;
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
