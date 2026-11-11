<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'site_id' => Site::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => strtoupper(fake()->unique()->bothify('SP-####')),
            'buy_url' => 'https://trangthietbi.vn/'.Str::slug($name),
            'is_active' => true,
        ];
    }
}
