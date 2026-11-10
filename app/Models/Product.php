<?php

namespace App\Models;

use App\Enums\ProductSource;
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

    /** Số ngày giá còn được hiển thị kể từ lần cập nhật. */
    public const PRICE_VALID_DAYS = 14;

    /** Cảnh báo trong trang quản trị khi giá còn hạn từ chừng này ngày trở xuống. */
    public const PRICE_WARNING_DAYS = 3;

    // price/price_updated_at chỉ đổi qua setPrice()/confirmPrice().
    protected $fillable = ['name', 'sku', 'specs', 'buy_url', 'slug', 'description', 'media_id', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'price' => 'integer',
            'price_updated_at' => 'datetime',
            'is_active' => 'boolean',
            'source' => ProductSource::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (blank($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    /** Đặt giá mới (hoặc bỏ giá); ngày cập nhật giá luôn đi kèm. */
    public function setPrice(?int $price): void
    {
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

    public function priceExpiresAt(): ?Carbon
    {
        return $this->price_updated_at?->copy()->addDays(self::PRICE_VALID_DAYS);
    }

    /** Giá được phép hiện ra: có giá và chưa quá 14 ngày kể từ lần cập nhật. */
    public function hasFreshPrice(): bool
    {
        $expiresAt = $this->priceExpiresAt();

        return $this->price !== null && $expiresAt !== null && now()->lessThanOrEqualTo($expiresAt);
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

    /** @return BelongsTo<Media, $this> */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
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

    /** Trang yêu cầu báo giá kèm sẵn sản phẩm (form RFQ: issue #10). */
    public function quoteRequestPath(): string
    {
        return '/yeu-cau-bao-gia?san-pham='.rawurlencode($this->sku);
    }
}
