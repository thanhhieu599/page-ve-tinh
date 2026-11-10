<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    protected $fillable = self::FIELDS;

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

    public function host(): string
    {
        return (string) parse_url((string) $this->value('url'), PHP_URL_HOST);
    }
}
