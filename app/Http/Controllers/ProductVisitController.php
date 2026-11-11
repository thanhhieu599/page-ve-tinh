<?php

namespace App\Http\Controllers;

use App\Models\OutboundClick;
use App\Models\Product;
use App\Support\CurrentSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /mua/{sku}: ghi một lượt bấm rồi chuyển ngay (302, không cache) sang sản phẩm trên web chính,
 * kèm UTM để web chính biết khách đến từ site vệ tinh nào, vị trí nào.
 */
class ProductVisitController extends Controller
{
    public const PREFIX = '/mua/';

    public function __invoke(Request $request, CurrentSite $currentSite, string $sku): RedirectResponse
    {
        $site = $currentSite->get() ?? abort(404);
        $product = Product::query()->active()->where('sku', $sku)->first();

        if ($product === null) {
            return redirect('/');
        }
        if (! $product->hasBuyUrl()) {
            return redirect($product->quoteRequestPath());
        }

        $placement = $request->query('vt');
        $placement = is_string($placement) && array_key_exists($placement, OutboundClick::PLACEMENTS) ? $placement : 'khac';

        if (preg_match(OutboundClick::BOT_PATTERN, (string) $request->userAgent()) !== 1) {
            $source = $request->query('tu');
            OutboundClick::query()->create([
                'product_id' => $product->id,
                'source_path' => is_string($source) && strlen($source) <= 300 && preg_match('#^/(?!/)[A-Za-z0-9/_.-]*$#', $source) ? $source : null,
                'placement' => $placement,
                'clicked_at' => now(),
            ]);
        }

        return redirect()->away($product->mainSiteUrl($site, $placement))
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
