<?php

namespace App\Support\Crm;

use App\Enums\LeadStatus;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;

/**
 * Gắn lead (từ bất kỳ site nào) vào khách hàng đã có, để một nhà máy gửi form ở nhiều site
 * vẫn là một khách: trùng email hoặc SĐT người liên hệ → đúng người, đúng công ty;
 * trùng tên miền email công ty (không phải gmail…) → đúng công ty.
 */
final class CustomerMatcher
{
    /** Tên miền email cá nhân, không dùng để nhận diện công ty. */
    public const FREE_DOMAINS = ['gmail.com', 'yahoo.com', 'yahoo.com.vn', 'hotmail.com', 'outlook.com', 'icloud.com', 'live.com', 'msn.com', 'proton.me', 'protonmail.com', 'zoho.com', 'ymail.com'];

    public static function attach(Lead $lead): void
    {
        if ($lead->customer_id !== null) {
            return;
        }

        $email = mb_strtolower(trim((string) $lead->email));
        $phone = Contact::digits($lead->phone);

        $contact = Contact::query()
            ->where(fn ($q) => $q->when($email !== '', fn ($q) => $q->orWhere('email', $email))
                ->when($phone !== null, fn ($q) => $q->orWhere('phone_digits', $phone)))
            ->when($email === '' && $phone === null, fn ($q) => $q->whereRaw('1 = 0'))
            ->oldest('id')->first();

        $customer = $contact?->customer;
        $domain = self::companyDomain($email);
        if ($customer === null && $domain !== null) {
            $customer = Customer::query()->where('email_domain', $domain)->oldest('id')->first();
        }

        if ($customer !== null) {
            $lead->forceFill([
                'customer_id' => $customer->id,
                'contact_id' => $contact?->id,
                // Khách đã có người phụ trách: lead mới về đúng người đó.
                'assigned_to' => $lead->assigned_to ?? $customer->owner_id,
                'assigned_at' => $lead->assigned_at ?? ($customer->owner_id ? now() : null),
            ])->saveQuietly();
        }
    }

    public static function companyDomain(?string $email): ?string
    {
        $domain = mb_strtolower((string) substr(strrchr((string) $email, '@') ?: '', 1));

        return $domain !== '' && ! in_array($domain, self::FREE_DOMAINS, true) ? $domain : null;
    }

    /** Chuyển lead thành khách hàng + người liên hệ (dùng lại khách đã có nếu khớp). */
    public static function convert(Lead $lead): Customer
    {
        self::attach($lead);
        $lead->refresh();

        $customer = $lead->customer ?? Customer::query()->create([
            'name' => $lead->company ?: $lead->name,
            'email_domain' => self::companyDomain($lead->email),
            'owner_id' => $lead->assigned_to,
            'first_site_id' => $lead->site_id,
        ]);

        $contact = $lead->contact ?? $customer->contacts()->create([
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'is_primary' => ! $customer->contacts()->exists(),
        ]);

        $lead->forceFill([
            'customer_id' => $customer->id,
            'contact_id' => $contact->id,
            'status' => $lead->status === LeadStatus::New ? LeadStatus::Qualified : $lead->status,
        ])->saveQuietly();

        return $customer;
    }
}
