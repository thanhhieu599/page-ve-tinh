<?php

namespace App\Models;

use App\Enums\PriceStatus;
use App\Enums\ProductSource;
use App\Http\Controllers\ProductVisitController;
use App\Models\Concerns\BelongsToSite;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sản phẩm giới thiệu trên site vệ tinh. Giá chỉ là giá tham khảo và chỉ hiện khi
 * còn hạn (cập nhật trong vòng 14 ngày); quá hạn thì mời khách yêu cầu báo giá.
 */
class Product extends Model
{
    use BelongsToSite;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public const PATH_PREFIX = '/san-pham/';

    /**
     * Giá cập nhật ngày D (giờ Việt Nam) được hiện đến hết ngày D + 14.
     * Tính theo ngày lịch, không theo số giờ, để giá không biến mất giữa ngày làm việc.
     */
    public const PRICE_VALID_DAYS = 14;

    /** Cảnh báo trong trang quản trị khi giá còn hiện từ chừng này ngày trở xuống. */
    public const PRICE_WARNING_DAYS = 3;

    /** Giá trần để chặn gõ nhầm (thừa số 0). */
    public const MAX_PRICE = 50_000_000_000;

    /** Link mua chỉ được trỏ về trangthietbi.vn (chính sách liên kết). */
    public const BUY_URL_PATTERN = '#^https://(www\.)?trangthietbi\.vn(/|$)#i';

    // price/price_updated_at chỉ đổi qua setPrice()/confirmPrice().
    protected $fillable = ['name', 'sku', 'specs', 'buy_url', 'slug', 'description', 'media_id', 'is_active', 'replacement_product_id', 'category_id', 'is_featured', 'brand_id', 'unit'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'specs' => 'array',
            'price' => 'integer',
            'price_updated_at' => 'datetime',
            'is_active' => 'boolean',
            'source' => ProductSource::class,
            'discontinued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            // Đường dẫn (chỉ dùng để chuyển hướng link /san-pham/ cũ): tự tạo từ tên, không trùng trong site.
            if (blank($product->slug)) {
                $base = Str::slug($product->name) ?: 'san-pham';
                $slug = $base;
                for ($i = 2; static::withoutGlobalScopes()->where('site_id', $product->site_id)->where('slug', $slug)->whereKeyNot($product->getKey())->exists(); $i++) {
                    $slug = $base.'-'.$i;
                }
                $product->slug = $slug;
            }
        });

        // Xoá sản phẩm: link /san-pham/ cũ chuyển về trang danh mục (không để khách gặp lỗi).
        static::deleted(function (Product $product): void {
            Redirect::recordMove($product->site_id, $product->path(), $product->category?->path() ?? '/');
        });

        // Đổi slug: URL sản phẩm cũ chuyển 301 sang URL mới.
        static::updated(function (Product $product): void {
            if ($product->wasChanged('slug')) {
                Redirect::recordMove($product->site_id, self::PATH_PREFIX.$product->getOriginal('slug'), $product->path());
            }
        });
    }

    public static function isValidPrice(int $price): bool
    {
        return $price >= 0 && $price <= self::MAX_PRICE;
    }

    public static function isAllowedBuyUrl(?string $url): bool
    {
        return blank($url) || preg_match(self::BUY_URL_PATTERN, (string) $url) === 1;
    }

    /** Đặt giá mới (0 hoặc null = bỏ giá); ngày cập nhật giá luôn đi kèm. */
    public function setPrice(?int $price): void
    {
        if ($price !== null && ! self::isValidPrice($price)) {
            throw new InvalidArgumentException("Giá không hợp lệ: {$price}");
        }

        $price = $price === 0 ? null : $price;
        $this->price = $price;
        $this->price_updated_at = $price === null ? null : now();
        $this->save();
    }

    /** Xác nhận giá hiện tại vẫn đúng: làm mới ngày cập nhật mà không đổi giá. */
    public function confirmPrice(): void
    {
        if ($this->price !== null) {
            $this->price_updated_at = now();
            $this->save();
        }
    }

    /** Đầu ngày (giờ Việt Nam) cách hôm nay $daysAgo ngày, quy về UTC để so với dữ liệu. */
    private static function vietnamDayStart(int $daysAgo): Carbon
    {
        return now()->timezone(config('app.display_timezone'))->startOfDay()->subDays($daysAgo)->utc();
    }

    /** Ngày cuối giá còn hiện (giờ Việt Nam). */
    public function priceShownUntil(): ?Carbon
    {
        return $this->price_updated_at?->copy()->timezone(config('app.display_timezone'))
            ->startOfDay()->addDays(self::PRICE_VALID_DAYS);
    }

    /** Giá được phép hiện ra: có giá và cập nhật trong 14 ngày lịch gần nhất (giờ Việt Nam). */
    public function hasFreshPrice(): bool
    {
        return $this->price !== null
            && $this->price_updated_at !== null
            && $this->price_updated_at->greaterThanOrEqualTo(self::vietnamDayStart(self::PRICE_VALID_DAYS));
    }

    public function priceStatus(): PriceStatus
    {
        return match (true) {
            $this->price === null => PriceStatus::None,
            ! $this->hasFreshPrice() => PriceStatus::Expired,
            $this->price_updated_at < self::vietnamDayStart(self::PRICE_VALID_DAYS - self::PRICE_WARNING_DAYS - 1) => PriceStatus::Expiring,
            default => PriceStatus::Fresh,
        };
    }

    /**
     * Còn hiện trên site nhưng chỉ còn PRICE_WARNING_DAYS ngày trở xuống.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function priceExpiringSoon(Builder $query): void
    {
        $query->whereNotNull('price')
            ->where('price_updated_at', '>=', self::vietnamDayStart(self::PRICE_VALID_DAYS))
            ->where('price_updated_at', '<', self::vietnamDayStart(self::PRICE_VALID_DAYS - self::PRICE_WARNING_DAYS - 1));
    }

    /**
     * Có giá nhưng đã hết hạn, đang ẩn trên site.
     *
     * @param  Builder<self>  $query
     */
    /** @param  Builder<self>  $query */
    #[Scope]
    protected function missingBuyUrl(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('buy_url')->orWhere('buy_url', ''));
    }

    #[Scope]
    protected function priceExpired(Builder $query): void
    {
        $query->whereNotNull('price')->where('price_updated_at', '<', self::vietnamDayStart(self::PRICE_VALID_DAYS));
    }

    public function formattedPrice(): string
    {
        return number_format((int) $this->price, 0, ',', '.').' đ';
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Media, $this> */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function replacement(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'replacement_product_id');
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsToMany<Post, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public function path(): string
    {
        return self::PATH_PREFIX.$this->slug;
    }

    public function hasBuyUrl(): bool
    {
        return filled($this->buy_url) && self::isAllowedBuyUrl($this->buy_url);
    }

    /**
     * Link khi khách bấm vào sản phẩm trên site vệ tinh: qua /mua/ (đếm lượt) rồi sang web chính.
     * Chưa có link web chính thì mở form báo giá.
     */
    public function visitUrl(string $placement, string $sourcePath): string
    {
        if (! $this->hasBuyUrl()) {
            return $this->quoteRequestPath();
        }

        // Mã viết thường: URL chuẩn của site luôn chữ thường (tránh thêm một bước chuyển 301). SKU không phân biệt hoa/thường.
        return ProductVisitController::PREFIX.rawurlencode(mb_strtolower($this->sku)).'?vt='.rawurlencode($placement).'&tu='.str_replace('%2F', '/', rawurlencode($sourcePath));
    }

    /** Link sản phẩm trên web chính kèm UTM (site nguồn, vị trí bấm). */
    public function mainSiteUrl(Site $site, string $placement): string
    {
        $utm = http_build_query([
            'utm_source' => $site->domain,
            'utm_medium' => 'site-ve-tinh',
            'utm_campaign' => 'san-pham',
            'utm_content' => $placement,
        ]);
        [$url, $fragment] = array_pad(explode('#', (string) $this->buy_url, 2), 2, null);

        return $url.(str_contains($url, '?') ? '&' : '?').$utm.($fragment !== null ? '#'.$fragment : '');
    }

    /** Trang yêu cầu báo giá kèm sẵn sản phẩm (form RFQ: issue #10). */
    public function quoteRequestPath(): string
    {
        return '/yeu-cau-bao-gia?san-pham='.rawurlencode($this->sku);
    }
}
