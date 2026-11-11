@extends('theme::layout')

@section('title', $product->name)

@section('meta_description', \Illuminate\Support\Str::limit((string) $product->description, 160))

@push('structured-data')
    {!! \App\Support\StructuredData::product($product, $site)->toScript() !!}
@endpush

@section('content')
    @include('theme::partials.breadcrumb', ['trail' => array_values(array_filter([
        $product->category ? [$product->category->name, $product->category->path()] : null,
        [$product->name, null],
    ]))])

    <article class="product-detail">
        <div class="product-detail-image">
            @if ($product->image)
                <img src="{{ $product->image->url() }}" srcset="{{ $product->image->srcset() }}"
                     sizes="(max-width: 768px) 100vw, 560px" alt="{{ $product->image->alt }}"
                     width="{{ $product->image->largestWidth() }}" height="{{ $product->image->heightAt($product->image->largestWidth()) }}"
                     fetchpriority="high" decoding="async">
            @else
                <span class="product-card-noimage">{{ $product->sku }}</span>
            @endif
        </div>

        <div class="product-detail-info">
            <h1>{{ $product->name }}</h1>
            <p class="sku">Mã: {{ $product->sku }}@if ($product->category) · Danh mục: <a href="{{ $product->category->path() }}">{{ $product->category->name }}</a>@endif</p>

            @include('theme::partials.product-price', ['product' => $product])

            <p class="actions">
                <a class="button button-primary" href="{{ $product->quoteRequestPath() }}">Yêu cầu báo giá theo số lượng</a>
                @if ($product->buy_url)
                    <a class="button" href="{{ $product->buy_url }}" rel="noopener">Xem trên trangthietbi.vn</a>
                @endif
            </p>

            <ul class="product-promises">
                <li>✓ Hoá đơn VAT đầy đủ</li>
                <li>✓ Giá theo số lượng cho doanh nghiệp</li>
                <li>✓ Giao tận văn phòng, nhà máy, KCN</li>
            </ul>
        </div>
    </article>

    @if ($product->description || ! empty($product->specs))
        <section class="product-detail-body">
            @if ($product->description)
                <h2 class="section-label">Mô tả sản phẩm</h2>
                <div class="description">{!! nl2br(e($product->description)) !!}</div>
            @endif

            @if (! empty($product->specs))
                <h2 class="section-label mt-8">Thông số</h2>
                <table class="specs">
                    @foreach ($product->specs as $label => $value)
                        <tr><th scope="row">{{ $label }}</th><td>{{ $value }}</td></tr>
                    @endforeach
                </table>
            @endif
        </section>
    @endif

    @if ($related->isNotEmpty())
        <section class="mt-12" aria-labelledby="san-pham-lien-quan">
            <h2 id="san-pham-lien-quan" class="section-label">Sản phẩm cùng danh mục</h2>
            <ul class="product-grid">
                @foreach ($related as $item)
                    @include('theme::partials.product-card', ['product' => $item])
                @endforeach
            </ul>
        </section>
    @endif
@endsection
