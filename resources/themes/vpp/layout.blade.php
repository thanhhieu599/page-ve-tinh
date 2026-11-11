<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') | {{ $site->name }}@else{{ $site->name }}@endif</title>
    <link rel="canonical" href="@hasSection('canonical')@yield('canonical')@else{{ \App\Support\CanonicalUrl::for($site, request()) }}@endif">
    {{-- Site chưa bật index: mọi trang noindex. Đã bật: trang tự khai @section('robots') nếu cần. --}}
    @php($robots = $site->is_indexable ? trim($__env->yieldContent('robots')) : \App\Models\Site::NOINDEX)
    @if ($robots !== '')
        <meta name="robots" content="{{ $robots }}">
    @endif
    {{-- Nội dung section đã được escape khi khai báo bằng @section('meta_description', ...). --}}
    @php($metaDescription = trim($__env->yieldContent('meta_description')))
    @if ($metaDescription !== '')
        <meta name="description" content="{!! $metaDescription !!}">
    @endif
    @if ($gscToken = $site->searchConsoleToken())
        <meta name="google-site-verification" content="{{ $gscToken }}">
    @endif
    @vite('resources/themes/'.\App\Support\Theme::resolve($site->theme).'/theme.css')
    @if ($brandColor = $site->brandColor())
        {{-- Màu thương hiệu riêng của site thay màu đỏ mặc định của theme. --}}
        <style>:root { --brand-color: {{ $brandColor }}; --color-brand: var(--brand-color); }</style>
    @endif
    @php($ga4Id = $site->ga4Id())
    @if ($ga4Id)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4Id }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json($ga4Id));
            // Bấm sản phẩm sang web chính trangthietbi.vn (link có data-outbound).
            document.addEventListener('click', function (e) {
                var a = e.target.closest && e.target.closest('a[data-outbound]');
                if (a) gtag('event', 'click_web_chinh', { placement: a.dataset.outbound, link_url: a.href, transport_type: 'beacon' });
            });
        </script>
    @endif
    {!! \App\Support\StructuredData::organization()->toScript() !!}
    @stack('structured-data')
    @stack('head-scripts')
</head>
<body>
    @include('theme::partials.header')

    <main class="container-site py-6 md:py-8">
        @yield('content')
    </main>

    @include('theme::partials.footer')
    @php($floatCompany = \App\Models\CompanyProfile::current())
    @php($zalo = collect($floatCompany->socialLinks())->firstWhere('key', 'zalo'))
    @if ($floatCompany->telHref() || $zalo)
        {{-- Nút liên hệ nhanh cố định góc phải (thói quen khách Việt: gọi/Zalo). --}}
        <div class="floating-contact">
            @if ($zalo)
                <a class="floating-zalo" href="{{ $zalo['url'] }}" rel="noopener" target="_blank" aria-label="Chat Zalo">Zalo</a>
            @endif
            @if ($floatCompany->telHref())
                <a class="floating-call" href="{{ $floatCompany->telHref() }}" aria-label="Gọi {{ $floatCompany->value('phone') }}"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></svg></a>
            @endif
        </div>
    @endif
</body>
</html>
