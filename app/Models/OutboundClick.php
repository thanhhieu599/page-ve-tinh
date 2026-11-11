<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một lượt bấm từ site vệ tinh sang sản phẩm trên web chính. */
class OutboundClick extends Model
{
    use BelongsToSite;

    public $timestamps = false;

    protected $fillable = ['product_id', 'source_path', 'placement', 'clicked_at'];

    /** Vị trí link: thẻ sản phẩm, cột phải, khối sản phẩm trong bài viết. Khác → "khac". */
    public const PLACEMENTS = ['the-sp' => 'Thẻ sản phẩm', 'cot-phai' => 'Cột phải', 'bai-viet' => 'Trong bài viết', 'khac' => 'Khác'];

    /** Trình thu thập dữ liệu, xem trước link: không tính là khách bấm. */
    public const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|monitor|curl|wget|python/i';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['clicked_at' => 'datetime'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
