<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    public function show(string $slug): View|RedirectResponse
    {
        $product = Product::query()->where('slug', $slug)->with(['image', 'replacement'])->firstOrFail();

        // Sản phẩm bị ẩn: chuyển 301 sang sản phẩm thay thế nếu có, không thì báo đã gỡ (410).
        if (! $product->is_active) {
            $replacement = $product->replacement;
            abort_unless($replacement?->is_active === true, 410);

            return redirect()->to($replacement->path(), 301);
        }

        return view('theme::products.show', ['product' => $product]);
    }
}
