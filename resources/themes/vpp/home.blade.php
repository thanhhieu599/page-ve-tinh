@extends('theme::layout')

@php($company = \App\Models\CompanyProfile::current())
@php($tagline = is_string($site->settings['tagline'] ?? null) && $site->settings['tagline'] !== ''
    ? $site->settings['tagline']
    : 'Cung cấp cho nhà máy và doanh nghiệp: báo giá theo số lượng, xuất hoá đơn VAT, giao tận nơi.')

@section('meta_description', $tagline)

@section('content')
    {{-- Lời chào hàng: nói rõ bán gì, cho ai, và một nút hành động chính. --}}
    <section class="-mx-4 -mt-6 bg-surface px-4 py-10 md:-mt-8 md:rounded-b-xl md:py-14">
        <div class="max-w-3xl">
            <h1 class="text-3xl md:text-4xl">{{ $site->name }}</h1>
            <p class="mt-4 text-lg text-muted md:text-xl">{{ $tagline }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a class="button button-primary text-lg" href="{{ \App\Http\Controllers\QuoteRequestController::PATH }}">Yêu cầu báo giá theo số lượng</a>
                @if ($phone = $company->value('phone'))
                    <a class="button text-lg" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">Gọi {{ $phone }}</a>
                @endif
            </div>
            <ul class="mt-6 grid gap-2 text-sm text-ink sm:grid-cols-3">
                <li>✔ Hoá đơn VAT đầy đủ</li>
                <li>✔ Giá theo số lượng cho nhà máy</li>
                <li>✔ Thuộc {{ $company->value('name') }} – {{ $company->host() }}</li>
            </ul>
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section class="mt-10" aria-labelledby="danh-muc">
            <h2 id="danh-muc" class="border-l-4 border-brand pl-3">Danh mục</h2>
            <ul class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ $category->path() }}" class="block h-full rounded-lg border border-line p-4 font-semibold text-ink no-underline transition hover:border-brand hover:text-brand">
                            {{ $category->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($products->isNotEmpty())
        <section class="mt-12" aria-labelledby="san-pham">
            <h2 id="san-pham" class="border-l-4 border-brand pl-3">Sản phẩm tiêu biểu</h2>
            <ul class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach ($products as $product)
                    <li class="flex flex-col rounded-lg border border-line p-3">
                        @if ($product->image)
                            <img src="{{ $product->image->url(480) }}" alt="{{ $product->image->alt }}"
                                 width="480" height="{{ $product->image->heightAt(480) }}"
                                 loading="lazy" decoding="async" class="aspect-square w-full rounded object-contain">
                        @endif
                        <h3 class="mt-2 text-base"><a class="text-ink no-underline hover:text-brand" href="{{ $product->path() }}">{{ $product->name }}</a></h3>
                        <div class="mt-auto">@include('theme::partials.product-price', ['product' => $product])</div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($posts->isNotEmpty())
        <section class="mt-12" aria-labelledby="kien-thuc">
            <h2 id="kien-thuc" class="border-l-4 border-brand pl-3">Kiến thức chọn mua</h2>
            <ul class="mt-4 grid gap-4 md:grid-cols-3">
                @foreach ($posts as $post)
                    <li class="rounded-lg border border-line p-4">
                        @if ($category = $post->primaryCategory())
                            <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ $category->name }}</p>
                        @endif
                        <h3 class="mt-1"><a class="text-ink no-underline hover:text-brand" href="{{ $post->path() }}">{{ $post->title }}</a></h3>
                        @if ($post->excerpt)
                            <p class="mt-2 text-sm text-muted">{{ \Illuminate\Support\Str::limit($post->excerpt, 140) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mt-12 rounded-xl bg-navy p-6 text-white md:p-8" aria-labelledby="vi-sao">
        <h2 id="vi-sao" class="text-white">Vì sao doanh nghiệp chọn {{ $company->value('name') }}</h2>
        <ul class="mt-4 grid gap-4 md:grid-cols-3">
            <li><strong class="block text-white">Báo giá nhanh theo số lượng</strong><span class="text-white/80">Gửi nhu cầu một lần, nhận báo giá phù hợp số lượng và lịch giao.</span></li>
            <li><strong class="block text-white">Chứng từ đầy đủ</strong><span class="text-white/80">Hoá đơn VAT, hồ sơ sản phẩm rõ nguồn gốc cho bộ phận mua hàng, QA.</span></li>
            <li><strong class="block text-white">Một đầu mối cho nhà máy</strong><span class="text-white/80">PPE, vật tư tiêu hao, văn phòng phẩm, quà tặng doanh nghiệp.</span></li>
        </ul>
    </section>

    <section class="quote-request" aria-labelledby="bao-gia">
        <h2 id="bao-gia">Yêu cầu báo giá</h2>
        <p class="mb-4 text-muted">Để lại thông tin, bộ phận kinh doanh sẽ liên hệ trong giờ làm việc.</p>
        @include('theme::partials.quote-form', ['product' => null])
    </section>
@endsection
