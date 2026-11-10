{{-- Giá tham khảo chỉ hiện khi còn hạn 14 ngày; không bao giờ hiện tồn kho. --}}
@if ($product->hasFreshPrice())
    <p class="price">
        <strong>{{ $product->formattedPrice() }}</strong><br>
        <small>Giá tham khảo, chưa gồm VAT. Cập nhật ngày {{ $product->price_updated_at->timezone(config('app.display_timezone'))->format('d/m/Y') }}.</small>
    </p>
@else
    <p class="price"><strong>Liên hệ báo giá</strong></p>
@endif
