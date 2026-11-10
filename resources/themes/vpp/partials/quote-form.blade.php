{{-- Form yêu cầu báo giá. Gửi được khi tắt JavaScript (HTML thuần). $product có thể null. --}}
<form method="POST" action="{{ \App\Http\Controllers\QuoteRequestController::PATH }}" class="quote-form" id="yeu-cau-bao-gia">
    @csrf
    <input type="hidden" name="page_url" value="{{ request()->getRequestUri() }}">
    @if ($product ?? null)
        <input type="hidden" name="product_sku" value="{{ $product->sku }}">
    @endif

    {{-- Bẫy bot: người thật không thấy ô này. --}}
    <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden">
        <label for="website">Website</label>
        <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
    </div>

    @if ($errors->any())
        <div class="form-errors" role="alert">
            <ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <p><label for="name">Họ tên *</label><br><input type="text" name="name" id="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name"></p>
    <p><label for="company">Công ty *</label><br><input type="text" name="company" id="company" value="{{ old('company') }}" required maxlength="255" autocomplete="organization"></p>
    <p><label for="email">Email *</label><br><input type="email" name="email" id="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"></p>
    <p><label for="phone">Số điện thoại *</label><br><input type="tel" name="phone" id="phone" value="{{ old('phone') }}" required maxlength="50" autocomplete="tel"></p>
    <p><label for="need">Nhu cầu (sản phẩm, số lượng, thời gian, địa điểm giao) *</label><br>
        <textarea name="need" id="need" rows="5" required maxlength="5000">{{ old('need', ($product ?? null) ? "Sản phẩm: {$product->name} ({$product->sku})\nSố lượng: " : '') }}</textarea></p>

    @if ($turnstileSiteKey = config('sites.turnstile.site_key'))
        <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}"></div>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif

    <p><label><input type="checkbox" name="consent" value="1" required>
        {{ \App\Support\Consent::TEXT }} <a href="{{ \App\Support\Consent::POLICY_PATH }}">Xem chính sách</a></label></p>

    <p><button type="submit">Gửi yêu cầu báo giá</button></p>
</form>
