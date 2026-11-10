<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ $site->name }}</title>
    {{-- Nội dung section đã được escape khi khai báo bằng @section('meta_description', ...). --}}
    @php($metaDescription = trim($__env->yieldContent('meta_description')))
    @if ($metaDescription !== '')
        <meta name="description" content="{!! $metaDescription !!}">
    @endif
</head>
<body>
    <header>
        <a href="/">{{ $site->name }}</a>
    </header>

    <main>
        @yield('content')
    </main>
</body>
</html>
