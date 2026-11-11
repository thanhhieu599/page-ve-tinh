<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;

/**
 * Link /san-pham/{slug} cũ: site vệ tinh không còn trang chi tiết sản phẩm (sản phẩm nằm trên web chính).
 * Chuyển 301 sang sản phẩm trên trangthietbi.vn; sản phẩm ẩn → sản phẩm thay thế hoặc trang danh mục.
 */
class ProductController extends Controller
{
    public function show(string $slug): RedirectResponse
    {
        $product = Product::query()->where('slug', $slug)->with(['replacement', 'category'])->firstOrFail();

        if (! $product->is_active) {
            $replacement = $product->replacement;
            if ($replacement?->is_active && $replacement->hasBuyUrl()) {
                return redirect()->away((string) $replacement->buy_url, 301);
            }

            return redirect()->to($product->category?->path() ?? '/', 301);
        }

        // Chưa có link web chính (tạm thời): mở form báo giá cho đúng mã.
        if (! $product->hasBuyUrl()) {
            return redirect()->to($product->quoteRequestPath(), 302);
        }

        return redirect()->away((string) $product->buy_url, 301);
    }
}
