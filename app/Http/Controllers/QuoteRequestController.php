<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CaptureAttribution;
use App\Models\Lead;
use App\Models\Product;
use App\Support\Consent;
use App\Support\CurrentSite;
use App\Support\Turnstile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class QuoteRequestController extends Controller
{
    public const PATH = '/yeu-cau-bao-gia';

    public function create(Request $request): View
    {
        $sku = $request->query('san-pham');
        $product = is_string($sku) ? Product::query()->active()->where('sku', $sku)->first() : null;

        return view('theme::quote-request.create', ['product' => $product]);
    }

    public function store(Request $request, CurrentSite $currentSite): RedirectResponse
    {
        $site = $currentSite->get() ?? abort(404);

        // Bẫy bot: ô ẩn có dữ liệu thì giả vờ thành công, không lưu.
        if (filled($request->input('website'))) {
            return redirect()->to(self::PATH.'/da-gui');
        }

        $limit = config('sites.quote_rate_limit');
        $rateKey = 'quote:'.$site->id.':'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($rateKey, $limit['attempts']), 429, 'Bạn đã gửi quá nhiều yêu cầu, vui lòng thử lại sau ít phút.');
        RateLimiter::hit($rateKey, $limit['decay_seconds']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'regex:/^[0-9+().\s-]{8,20}$/'],
            'need' => ['required', 'string', 'max:5000'],
            'consent' => ['accepted'],
            'product_sku' => ['nullable', 'string', 'max:100'],
            'page_url' => ['nullable', 'string', 'max:500', 'starts_with:/'],
        ], [
            'consent.accepted' => 'Vui lòng đồng ý với việc xử lý thông tin để chúng tôi liên hệ báo giá.',
            'phone.regex' => 'Số điện thoại chưa đúng.',
        ]);

        // Có token Turnstile thì phải hợp lệ; không có (tắt JavaScript) thì nhận nhưng ghi "chưa xác minh".
        $token = $request->input('cf-turnstile-response');
        $isVerified = false;
        if (Turnstile::enabled() && is_string($token) && $token !== '') {
            if (! Turnstile::verify($token, $request->ip())) {
                throw ValidationException::withMessages([
                    'cf-turnstile-response' => 'Không xác minh được bạn không phải robot. Vui lòng thử lại.',
                ]);
            }
            $isVerified = true;
        }

        $attribution = (array) $request->session()->get(CaptureAttribution::SESSION_KEY, []);
        $product = filled($data['product_sku'] ?? null)
            ? Product::query()->where('sku', $data['product_sku'])->first()
            : null;

        $lead = new Lead([
            ...collect($data)->only(['name', 'company', 'email', 'phone', 'need', 'page_url'])->all(),
            'product_id' => $product?->id,
            'landing_url' => $attribution['landing_url'] ?? null,
            'utm_source' => $attribution['utm_source'] ?? null,
            'utm_medium' => $attribution['utm_medium'] ?? null,
            'utm_campaign' => $attribution['utm_campaign'] ?? null,
        ]);
        $lead->site_id = $site->id;
        $lead->is_verified = $isVerified;
        $lead->consent_text = Consent::TEXT;
        $lead->consent_at = now();
        $lead->ip = $request->ip();
        $lead->save();

        return redirect()->to(self::PATH.'/da-gui');
    }

    public function thanks(): View
    {
        return view('theme::quote-request.thanks');
    }
}
