<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Thông tin D&T dùng chung cho mọi site (footer, trang giới thiệu, liên hệ, schema).
 * Một dòng duy nhất, Admin sửa trong trang quản trị. Mục chưa nhập lấy mặc định từ
 * config/company.php (biến môi trường COMPANY_*).
 */
class CompanyProfile extends Model
{
    protected $table = 'company_profile';

    public const FIELDS = ['name', 'legal_name', 'url', 'logo_url', 'tax_id', 'phone', 'email', 'street_address', 'locality', 'region'];

    /** Cột trong bảng → khoá trong config/company.php (khi tên khác nhau). */
    private const CONFIG_KEYS = ['logo_url' => 'logo'];

    /** Danh sách hiện ở footer (JSON). */
    public const LIST_FIELDS = ['addresses', 'contacts', 'socials', 'payment_methods', 'bct_badge', 'bct_url'];

    /** Mạng xã hội được hiện ở footer (khoá → tên hiển thị). */
    public const SOCIALS = ['facebook' => 'Facebook', 'youtube' => 'YouTube', 'zalo' => 'Zalo', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn'];

    public const BADGE_DIRECTORY = 'company';

    /** Cài đặt báo giá/đơn hàng của CRM (CRM → Cài đặt). */
    public const CRM_FIELDS = ['quote_vat_rate', 'quote_valid_days', 'quote_delivery_terms', 'quote_payment_terms', 'bank_account', 'default_reorder_cycle_days', 'payment_due_days'];

    protected $fillable = [...self::FIELDS, ...self::LIST_FIELDS, ...self::CRM_FIELDS];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['addresses' => 'array', 'contacts' => 'array', 'socials' => 'array', 'payment_methods' => 'array', 'quote_vat_rate' => 'decimal:2', 'quote_valid_days' => 'integer', 'default_reorder_cycle_days' => 'integer', 'payment_due_days' => 'integer'];
    }

    /** Tên trong container: đọc một lần mỗi request (scoped), không giữ sang request sau. */
    public const CURRENT = 'company-profile.current';

    protected static function booted(): void
    {
        static::saved(fn () => app()->forgetInstance(self::CURRENT));
    }

    /** Bản ghi đang lưu, hoặc bản rỗng (mọi mục lấy mặc định) nếu chưa lưu lần nào. */
    public static function stored(): self
    {
        return static::query()->oldest('id')->first() ?? new self;
    }

    /** Thông tin công ty của request hiện tại (đăng ký scoped trong AppServiceProvider). */
    public static function current(): self
    {
        return app(self::CURRENT);
    }

    /** Giá trị một mục: đã nhập trong trang quản trị thì dùng, không thì lấy mặc định cấu hình. */
    public function value(string $field): ?string
    {
        $stored = $this->getAttribute($field);
        $value = filled($stored) ? $stored : config('company.'.(self::CONFIG_KEYS[$field] ?? $field));

        return filled($value) ? (string) $value : null;
    }

    /** @return array<string, ?string> mọi mục, đã áp mặc định */
    public function values(): array
    {
        return array_combine(self::FIELDS, array_map($this->value(...), self::FIELDS));
    }

    /** Địa chỉ một dòng: "số nhà, đường, phường/quận, tỉnh/thành". */
    public function address(): ?string
    {
        $address = implode(', ', array_filter([$this->value('street_address'), $this->value('locality'), $this->value('region')]));

        return $address !== '' ? $address : null;
    }

    /** Link gọi điện (tel:) từ số điện thoại công ty, chỉ giữ chữ số và dấu +. */
    public function telHref(): ?string
    {
        $phone = $this->value('phone');

        return $phone !== null ? 'tel:'.preg_replace('/[^0-9+]/', '', $phone) : null;
    }

    /** @return list<array{label: string, address: string}> địa chỉ khác (văn phòng giao dịch, kho…) */
    public function otherAddresses(): array
    {
        return array_values(array_filter(array_map(fn ($row) => is_array($row) && filled($row['address'] ?? null)
            ? ['label' => trim((string) ($row['label'] ?? '')), 'address' => trim((string) $row['address'])] : null,
            (array) $this->addresses)));
    }

    /** @return list<array{name: string, phone: string, tel: string}> người liên hệ kèm số điện thoại */
    public function contactPeople(): array
    {
        return array_values(array_filter(array_map(fn ($row) => is_array($row) && filled($row['phone'] ?? null)
            ? ['name' => trim((string) ($row['name'] ?? '')), 'phone' => trim((string) $row['phone']), 'tel' => 'tel:'.preg_replace('/[^0-9+]/', '', (string) $row['phone'])] : null,
            (array) $this->contacts)));
    }

    /** @return list<array{key: string, label: string, url: string}> chỉ link https */
    public function socialLinks(): array
    {
        $links = [];
        foreach (self::SOCIALS as $key => $label) {
            $url = $this->socials[$key] ?? null;
            if (is_string($url) && str_starts_with($url, 'https://')) {
                $links[] = ['key' => $key, 'label' => $label, 'url' => $url];
            }
        }

        return $links;
    }

    /** @return list<string> hình thức thanh toán; chưa nhập thì dùng mặc định cấu hình */
    public function paymentMethodList(): array
    {
        $methods = array_values(array_filter(array_map('trim', array_map('strval', (array) $this->payment_methods))));

        return $methods !== [] ? $methods : (array) config('company.payment_methods', []);
    }

    /** Logo "Đã thông báo Bộ Công Thương": chỉ khi Admin đã tải ảnh lên. @return array{url: string, href: ?string, width: int, height: int}|null */
    public function bctBadge(int $displayWidth = 150): ?array
    {
        $path = $this->bct_badge;
        $disk = Storage::disk('public');
        if (! is_string($path) || ! str_starts_with($path, self::BADGE_DIRECTORY.'/') || ! $disk->exists($path)) {
            return null;
        }
        $size = @getimagesize($disk->path($path));
        $height = $size !== false && $size[0] > 0 ? (int) round($size[1] * $displayWidth / $size[0]) : (int) round($displayWidth * 0.38);
        $href = is_string($this->bct_url) && preg_match('#^https?://(www\.)?online\.gov\.vn/#', $this->bct_url) ? $this->bct_url : null;

        return ['url' => $disk->url($path), 'href' => $href, 'width' => $displayWidth, 'height' => $height];
    }

    public function host(): string
    {
        return (string) parse_url((string) $this->value('url'), PHP_URL_HOST);
    }
}
