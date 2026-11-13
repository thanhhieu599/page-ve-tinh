<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một dòng hàng của đơn hàng. */
class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'sku', 'name', 'unit', 'quantity', 'unit_price', 'line_total', 'sort'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_price' => 'integer', 'line_total' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(fn (OrderItem $item) => $item->line_total = (int) round((float) $item->quantity * (int) $item->unit_price));
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }
}
