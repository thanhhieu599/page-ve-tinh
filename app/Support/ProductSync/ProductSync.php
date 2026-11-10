<?php

namespace App\Support\ProductSync;

use App\Enums\ProductSource;
use App\Models\Product;
use App\Models\Scopes\SiteScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bộ đồng bộ sản phẩm dùng chung cho mọi nguồn (CSV, Sapo API).
 *
 * Quy tắc ghi đè:
 * - Khớp theo SKU trong cùng site.
 * - Nguồn quyết định: tên, SKU, thông số, link mua, giá (kèm ngày cập nhật giá).
 * - CMS quyết định, không bao giờ ghi đè: slug, mô tả, ảnh, liên kết bài, FAQ.
 * - Ngừng bán → ẩn, giữ dữ liệu. Bán lại → hiện lại (chỉ nếu bị ẩn vì ngừng bán).
 */
final class ProductSync
{
    /**
     * @param  iterable<int, ProductRecord|string>  $records  khoá là số dòng; chuỗi là lỗi đọc dòng đó
     * @param  bool  $fullFile  nguồn liệt kê đủ mọi sản phẩm: SKU vắng mặt coi là ngừng bán
     */
    public function sync(int $siteId, iterable $records, ProductSource $source, bool $fullFile = false): SyncReport
    {
        $report = new SyncReport;
        $seenSkus = [];

        DB::transaction(function () use ($siteId, $records, $source, $fullFile, $report, &$seenSkus): void {
            foreach ($records as $row => $record) {
                if (is_string($record)) {
                    $report->addError($row, null, $record);

                    continue;
                }

                $error = $this->validate($record);
                if ($error !== null) {
                    $report->addError($row, $record->sku, $error);

                    continue;
                }

                $seenSkus[] = $record->sku;
                $this->apply($siteId, $record, $source, $report, $row);
            }

            if ($fullFile) {
                $this->hideMissing($siteId, $seenSkus, $report);
            }
        });

        return $report;
    }

    private function validate(ProductRecord $record): ?string
    {
        return match (true) {
            trim($record->sku) === '' => 'Thiếu mã SKU',
            $record->price !== null && ($record->price < 0 || $record->price > Product::MAX_PRICE) => 'Giá không hợp lệ',
            ! Product::isAllowedBuyUrl($record->buyUrl) => 'Link mua phải là trang trên https://trangthietbi.vn',
            default => null,
        };
    }

    private function apply(int $siteId, ProductRecord $record, ProductSource $source, SyncReport $report, int $row): void
    {
        $product = $this->forSite($siteId)->where('sku', $record->sku)->first();

        if ($product === null) {
            if (blank($record->name)) {
                $report->addError($row, $record->sku, 'Thiếu tên sản phẩm');

                return;
            }

            $product = new Product;
            $product->site_id = $siteId;
            $product->sku = $record->sku;
            $product->slug = $this->uniqueSlug($siteId, (string) $record->name);
            $product->is_active = true;
            $isNew = true;
        } else {
            $isNew = false;
        }

        if (filled($record->name)) {
            $product->name = (string) $record->name;
        }
        if ($record->buyUrl !== null) {
            $product->buy_url = $record->buyUrl === '' ? null : $record->buyUrl;
        }
        if ($record->specs !== null) {
            $product->specs = $record->specs;
        }
        $product->source = $source;

        $wasActive = $isNew || $product->is_active;
        if ($record->discontinued) {
            $product->is_active = false;
            $product->discontinued_at ??= now();
        } elseif ($product->discontinued_at !== null) {
            $product->is_active = true;
            $product->discontinued_at = null;
        }

        $product->save();

        if ($record->price !== null) {
            $product->setPrice($record->price);
        }

        if ($isNew) {
            $report->created++;
        } else {
            $report->updated++;
        }
        if ($wasActive && ! $product->is_active) {
            $report->hidden++;
        }
    }

    /** @param  list<string>  $seenSkus */
    private function hideMissing(int $siteId, array $seenSkus, SyncReport $report): void
    {
        // Sản phẩm tạo tay (không đến từ nguồn) không bị ẩn chỉ vì vắng mặt trong file.
        $missing = $this->forSite($siteId)
            ->where('is_active', true)
            ->where('source', '!=', ProductSource::Manual->value)
            ->whereNotIn('sku', $seenSkus)
            ->get();

        foreach ($missing as $product) {
            $product->is_active = false;
            $product->discontinued_at = now();
            $product->save();
            $report->hidden++;
        }
    }

    /** @return Builder<Product> */
    private function forSite(int $siteId): Builder
    {
        return Product::withoutGlobalScope(SiteScope::class)->where('site_id', $siteId);
    }

    private function uniqueSlug(int $siteId, string $name): string
    {
        $base = Str::slug($name) ?: 'san-pham';
        $slug = $base;
        $suffix = 2;

        while ($this->forSite($siteId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
