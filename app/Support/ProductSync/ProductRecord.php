<?php

namespace App\Support\ProductSync;

/**
 * Một sản phẩm từ nguồn dữ liệu gốc (CSV Sapo, sau này Sapo API), đã chuẩn hoá.
 * null nghĩa là nguồn không có thông tin đó: giữ nguyên giá trị hiện có.
 */
final readonly class ProductRecord
{
    /**
     * @param  array<string, string>|null  $specs
     */
    public function __construct(
        public string $sku,
        public ?string $name = null,
        public ?int $price = null,
        public ?string $buyUrl = null,
        public ?array $specs = null,
        public bool $discontinued = false,
    ) {}
}
