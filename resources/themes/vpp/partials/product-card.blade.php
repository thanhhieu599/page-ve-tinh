{{--
    Thẻ sản phẩm: ảnh vuông, nhãn nổi bật, danh mục, tên, giá tham khảo (hoặc "Liên hệ báo giá"), nút báo giá riêng.
    Bấm ảnh/tên: mở sản phẩm trên web chính trangthietbi.vn ở tab mới (qua /mua/ để đếm lượt); chưa có link thì mở form báo giá.
--}}
<li class="product-card">
    <a href="{{ $product->visitUrl('the-sp', request()->getPathInfo()) }}" class="product-card-link"@if ($product->hasBuyUrl()) target="_blank" rel="noopener" data-outbound="the-sp"@endif>
        <span class="product-card-image">
            @if ($product->is_featured)<span class="product-badge">Nổi bật</span>@endif
            @if ($product->image)
                <img src="{{ $product->image->url(480) }}" alt="{{ $product->image->alt }}"
                     width="480" height="{{ $product->image->heightAt(480) }}" loading="lazy" decoding="async">
            @else
                <span class="product-card-noimage" aria-hidden="true">{{ $product->sku }}</span>
            @endif
        </span>
        @if ($product->category)
            <span class="product-card-category">{{ $product->category->name }}</span>
        @endif
        <span class="product-card-name">{{ $product->name }}</span>
        <span class="product-card-price">{{ $product->hasFreshPrice() ? $product->formattedPrice() : 'Liên hệ báo giá' }}</span>
    </a>
    <a class="product-card-quote" href="{{ $product->quoteRequestPath() }}" aria-label="Báo giá {{ $product->name }}">Báo giá số lượng</a>
</li>
