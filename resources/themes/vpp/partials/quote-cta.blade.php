{{-- Lời mời báo giá cuối trang: dẫn sang trang báo giá riêng, mang theo trang nguồn để đo bài nào ra lead. --}}
@php($company = \App\Models\CompanyProfile::current())
<section class="quote-cta" aria-labelledby="loi-moi-bao-gia">
    <div>
        <h2 id="loi-moi-bao-gia">{{ $heading ?? 'Cần báo giá theo số lượng cho doanh nghiệp?' }}</h2>
        <p>Gửi danh sách vật tư, số lượng và địa điểm giao, bộ phận kinh doanh phản hồi trong giờ làm việc. Hoá đơn VAT đầy đủ.</p>
    </div>
    <div class="quote-cta-actions">
        <a class="button rounded-full border-white bg-white px-6 py-3 text-brand hover:bg-white/90 hover:text-brand-dark" href="{{ \App\Http\Controllers\QuoteRequestController::pathFrom(request()->getPathInfo()) }}">Gửi yêu cầu báo giá</a>
        @if ($company->value('phone'))
            <a class="quote-cta-phone" href="{{ $company->telHref() }}">Hoặc gọi {{ $company->value('phone') }}</a>
        @endif
    </div>
</section>
