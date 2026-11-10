<?php

namespace App\Support\ProductSync;

use App\Enums\ProductSource;
use App\Models\Product;
use App\Models\Scopes\SiteScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

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
        if ($siteId <= 0) {
            throw new InvalidArgumentException('Đồng bộ sản phẩm phải chỉ định một site.');
        }

        $report = new SyncReport;

        // Cả lần nhập là một giao dịch: lỗi giữa chừng thì không có thay đổi dở dang nào.
        DB::transaction(function () use ($siteId, $records, $source, $fullFile, $report): void {
            /** @var array<string, int> $seenSkus SKU (chữ thường) → dòng đầu tiên */
            $seenSkus = [];
            $fileIsUsable = true;

            foreach ($records as $row => $record) {
                if (is_string($record)) {
                    $report->addError($row, null, $record);
                    // Lỗi đọc cả file (dòng tiêu đề): không đủ căn cứ để ẩn gì cả.
                    $fileIsUsable = $fileIsUsable && $row !== 1;

                    continue;
                }

                $key = mb_strtolower(trim($record->sku));
                if ($key !== '' && isset($seenSkus[$key])) {
                    $report->addError($row, $record->sku, "SKU trùng với dòng {$seenSkus[$key]}, bỏ qua dòng này");

                    continue;
                }
                if ($key !== '') {
                    // Dòng lỗi vẫn tính là "có trong file", để chế độ file đầy đủ không ẩn nhầm.
                    $seenSkus[$key] = $row;
                }

                $error = $this->validate($record);
                if ($error !== null) {
                    $report->addError($row, $record->sku, $error);

                    continue;
                }

                $this->apply($siteId, $record, $source, $report, $row);
            }

            if ($fullFile && $fileIsUsable && $seenSkus !== []) {
                $this->hideMissing($siteId, array_keys($seenSkus), $report);
            }
        });

        return $report;
    }

    private function validate(ProductRecord $record): ?string
    {
        return match (true) {
            trim($record->sku) === '' => 'Thiếu mã SKU',
            mb_strlen($record->sku) > 100 => 'Mã SKU dài quá 100 ký tự',
            $record->name !== null && mb_strlen($record->name) > 255 => 'Tên sản phẩm dài quá 255 ký tự',
            $record->price !== null && ! Product::isValidPrice($record->price) => 'Giá không hợp lệ',
            ! Product::isAllowedBuyUrl($record->buyUrl) || mb_strlen((string) $record->buyUrl) > 255 => 'Link mua phải là trang trên https://trangthietbi.vn',
            default => null,
        };
    }

    private function apply(int $siteId, ProductRecord $record, ProductSource $source, SyncReport $report, int $row): void
    {
        $product = $this->forSite($siteId)->where('sku', trim($record->sku))->first();

        if ($product === null) {
            if (blank($record->name)) {
                $report->addError($row, $record->sku, 'Thiếu tên sản phẩm');

                return;
            }

            $product = new Product;
            $product->site_id = $siteId;
            $product->sku = trim($record->sku);
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

        // "Ẩn" chỉ đếm sản phẩm đang hiện bị ẩn đi, không đếm sản phẩm mới tạo đã ngừng bán.
        $wasActive = ! $isNew && $product->is_active;
        if ($record->discontinued) {
            $this->discontinue($product);
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
            ->get()
            ->reject(fn (Product $product): bool => in_array(mb_strtolower(trim($product->sku)), $seenSkus, true));

        foreach ($missing as $product) {
            $this->discontinue($product);
            $product->save();
            $report->hidden++;
        }
    }

    /** Ẩn vì ngừng bán trên nguồn (khác ẩn tay: có thể tự hiện lại khi bán lại). */
    private function discontinue(Product $product): void
    {
        $product->is_active = false;
        $product->discontinued_at ??= now();
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
