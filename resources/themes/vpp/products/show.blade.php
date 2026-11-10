@extends('theme::layout')

@section('title', $product->name)

@section('meta_description', \Illuminate\Support\Str::limit((string) $product->description, 160))

@section('content')
    @include('theme::partials.breadcrumb', ['trail' => [[$product->name, null]]])

    <article class="product">
        <h1>{{ $product->name }}</h1>
        <p class="sku">Mã: {{ $product->sku }}</p>

        @if ($product->image)
            <img src="{{ $product->image->url() }}" srcset="{{ $product->image->srcset() }}"
                 sizes="(max-width: 800px) 100vw, 800px" alt="{{ $product->image->alt }}"
                 width="{{ $product->image->largestWidth() }}" height="{{ $product->image->heightAt($product->image->largestWidth()) }}"
                 fetchpriority="high" decoding="async">
        @endif

        @include('theme::partials.product-price', ['product' => $product])

        <p class="actions">
            <a class="button button-primary" href="{{ $product->quoteRequestPath() }}">Yêu cầu báo giá theo số lượng</a>
            @if ($product->buy_url)
                <a class="button" href="{{ $product->buy_url }}" rel="noopener">Xem trên trangthietbi.vn</a>
            @endif
        </p>

        @if ($product->description)
            <div class="description">{!! nl2br(e($product->description)) !!}</div>
        @endif

        @if (! empty($product->specs))
            <h2>Thông số</h2>
            <table class="specs">
                @foreach ($product->specs as $label => $value)
                    <tr><th scope="row">{{ $label }}</th><td>{{ $value }}</td></tr>
                @endforeach
            </table>
        @endif
    </article>
@endsection
