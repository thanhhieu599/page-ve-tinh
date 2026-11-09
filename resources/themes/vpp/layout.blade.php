<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ $site->name }}</title>
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
