@extends('theme::layout')

@php($company = \App\Models\CompanyProfile::current())
@php($tagline = $site->tagline() ?? 'Cung cấp cho nhà máy và doanh nghiệp: báo giá theo số lượng, xuất hoá đơn VAT, giao tận nơi.')
@php($categoryTree = \App\Support\SiteMenu::categoryTree())

@section('meta_description', $tagline)

@section('content')
    <div class="grid gap-6 md:grid-cols-[16rem_1fr]">
        {{-- Danh mục sản phẩm (cột trái, máy tính). Trên điện thoại dùng mục "Danh mục sản phẩm" ở thanh đỏ. --}}
        @if ($categoryTree !== [])
            <aside class="hidden md:block" aria-label="Danh mục sản phẩm">
                <ul class="category-sidebar">
                    @foreach ($categoryTree as $root)
                        <li class="category-menu-item">
                            <a href="{{ $root['href'] }}">{{ $root['label'] }}@if ($root['children'] !== [])<span aria-hidden="true">›</span>@endif</a>
                            @if ($root['children'] !== [])
                                <ul class="category-menu-sub">
                                    @foreach ($root['children'] as $child)
                                        <li><a href="{{ $child['href'] }}">{{ $child['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </aside>
        @endif

        @php($banners = $site->banners())
        @if ($banners !== [])
            {{-- Banner tải lên trong "Cài đặt site": trượt ngang bằng CSS (vuốt/bấm chấm, chạy khi tắt JavaScript). --}}
            <section class="banner-slider-wrap @if ($categoryTree === []) md:col-span-2 @endif" aria-label="Banner">
                <h1 class="sr-only">{{ $site->name }}</h1>
                <div class="banner-slider" data-autoplay>
                    @foreach ($banners as $i => $banner)
                        <div class="banner-slide" id="banner-{{ $i + 1 }}">
                            @if ($banner['href'])<a href="{{ $banner['href'] }}" class="block">@endif
                            <img src="{{ $banner['url'] }}" alt="{{ $banner['alt'] }}"@if ($banner['width']) width="{{ $banner['width'] }}" height="{{ $banner['height'] }}"@endif {!! $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' !!} decoding="async">
                            @if ($banner['href'])</a>@endif
                        </div>
                    @endforeach
                </div>
                @if (count($banners) > 1)
                    <nav class="banner-dots" aria-label="Chọn banner">
                        @foreach ($banners as $i => $banner)
                            <a href="#banner-{{ $i + 1 }}" aria-label="Banner {{ $i + 1 }}"></a>
                        @endforeach
                    </nav>
                    {{-- Tự chuyển banner (tăng cường; không có JavaScript vẫn vuốt/bấm được). --}}
                    <script>
                        (() => {
                            const slider = document.currentScript.parentElement.querySelector('[data-autoplay]');
                            if (!slider || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                            setInterval(() => {
                                const atEnd = slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 4;
                                slider.scrollTo({ left: atEnd ? 0 : slider.scrollLeft + slider.clientWidth, behavior: 'smooth' });
                            }, 5000);
                        })();
                    </script>
                @endif
            </section>
        @else
            {{-- Chưa có banner: khối chữ chào hàng (Google/AI đọc được, tải nhanh). --}}
            <section class="home-banner @if ($categoryTree === []) md:col-span-2 @endif">
                <p class="text-sm font-semibold uppercase tracking-widest text-white/80">Thuộc {{ $company->value('name') }} · {{ $company->host() }}</p>
                <h1 class="mt-3 text-3xl text-white md:text-5xl">{{ $site->name }}</h1>
                <p class="mt-4 max-w-xl text-lg text-white/90">{{ $tagline }}</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a class="button rounded-full border-white bg-white px-6 py-3 text-lg text-brand hover:bg-white/90 hover:text-brand-dark" href="{{ \App\Http\Controllers\QuoteRequestController::PATH }}">Yêu cầu báo giá theo số lượng</a>
                </div>
            </section>
        @endif
    </div>

    {{-- Dải cam kết (kiểu web thương mại). --}}
    <ul class="usp-bar">
        <li><span class="usp-icon" aria-hidden="true">₫</span><span><strong>Giá theo số lượng</strong><small>Báo giá riêng cho doanh nghiệp</small></span></li>
        <li><span class="usp-icon" aria-hidden="true">✓</span><span><strong>Hoá đơn VAT đầy đủ</strong><small>Chứng từ rõ nguồn gốc</small></span></li>
        <li><span class="usp-icon" aria-hidden="true">⛟</span><span><strong>Giao tận nơi</strong><small>Văn phòng, nhà máy, KCN</small></span></li>
        <li><span class="usp-icon" aria-hidden="true">☎</span><span><strong>{{ $company->value('phone') ?? 'Hỗ trợ báo giá' }}</strong><small>Tư vấn và báo giá nhanh</small></span></li>
    </ul>

    @if ($featured->isNotEmpty())
        <section class="mt-12" aria-labelledby="noi-bat">
            <h2 id="noi-bat" class="section-label">Sản phẩm nổi bật</h2>
            <ul class="product-grid">
                @foreach ($featured as $product)
                    @include('theme::partials.product-card', ['product' => $product])
                @endforeach
            </ul>
        </section>
    @endif

    @foreach ($shelves as $shelf)
        @php($shelfId = 'danh-muc-'.$shelf['category']->slug)
        <section class="mt-12" aria-labelledby="{{ $shelfId }}">
            <h2 id="{{ $shelfId }}" class="section-label">{{ $shelf['category']->name }}</h2>
            <ul class="product-grid">
                @foreach ($shelf['products'] as $product)
                    @include('theme::partials.product-card', ['product' => $product])
                @endforeach
            </ul>
            <p class="mt-5 text-center"><a class="button button-primary rounded-full px-6 text-sm" href="{{ $shelf['category']->path() }}">Xem tất cả</a></p>
        </section>
    @endforeach

    @if ($posts->isNotEmpty())
        <section class="mt-12" aria-labelledby="kien-thuc">
            <h2 id="kien-thuc" class="section-label">Tin tức</h2>
            <ul class="mt-4 grid gap-4 md:grid-cols-3">
                @foreach ($posts as $post)
                    <li class="rounded-md border border-line bg-white p-4 shadow-sm">
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
            <p class="mt-5 text-center"><a class="button rounded-full px-6 text-sm" href="{{ \App\Http\Controllers\ArticleController::PATH }}">Xem tất cả tin tức</a></p>
        </section>
    @endif

    <section class="mt-12 rounded-md bg-accent p-6 text-white md:p-8" aria-labelledby="vi-sao">
        <h2 id="vi-sao" class="text-white">Vì sao doanh nghiệp chọn {{ $company->value('name') }}</h2>
        <ul class="mt-4 grid gap-4 md:grid-cols-3">
            <li><strong class="block text-white">Báo giá nhanh theo số lượng</strong><span class="text-white/80">Gửi nhu cầu một lần, nhận báo giá phù hợp số lượng và lịch giao.</span></li>
            <li><strong class="block text-white">Chứng từ đầy đủ</strong><span class="text-white/80">Hoá đơn VAT, hồ sơ sản phẩm rõ nguồn gốc cho bộ phận mua hàng, QA.</span></li>
            <li><strong class="block text-white">Một đầu mối cho nhà máy</strong><span class="text-white/80">PPE, vật tư tiêu hao, văn phòng phẩm, quà tặng doanh nghiệp.</span></li>
        </ul>
    </section>

    @include('theme::partials.quote-cta')
@endsection
