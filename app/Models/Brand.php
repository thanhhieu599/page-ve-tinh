<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Thương hiệu sản phẩm (TOWA, Deli, Thiên Long, 3M…), dùng chung mọi site. */
class Brand extends Model
{
    protected $fillable = ['name', 'website', 'description'];

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->withoutGlobalScopes();
    }
}
