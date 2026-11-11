{{-- Thẻ sản phẩm dạng lưới: ảnh vuông, danh mục, tên, giá tham khảo (hoặc "Liên hệ báo giá"). --}}
<li class="product-card">
    <a href="{{ $product->path() }}" class="product-card-link">
        <span class="product-card-image">
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
</li>
