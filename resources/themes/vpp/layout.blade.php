<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ $site->name }}</title>
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
    @if ($brandColor = $site->brandColor())
        <style>:root { --brand-color: {{ $brandColor }}; }</style>
    @endif
    @php($ga4Id = $site->ga4Id())
    @if ($ga4Id)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4Id }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json($ga4Id));
        </script>
    @endif
    {!! \App\Support\StructuredData::organization()->toScript() !!}
    @stack('structured-data')
    @stack('head-scripts')
</head>
<body>
    <header>
        <a href="/">
            @if ($logo = $site->logoUrl())
                <img src="{{ $logo }}" alt="{{ $site->name }}" height="48">
            @else
                {{ $site->name }}
            @endif
        </a>
    </header>

    <main>
        @yield('content')
    </main>

    @include('theme::partials.footer')
</body>
</html>
