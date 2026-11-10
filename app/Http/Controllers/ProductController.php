<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

class ProductController extends Controller
{
    public function show(string $slug): View
    {
        $product = Product::query()->active()->where('slug', $slug)->with('image')->firstOrFail();

        return view('theme::products.show', ['product' => $product]);
    }
}
