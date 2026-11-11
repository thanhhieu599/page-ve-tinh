<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ \App\Models\Site::NOINDEX }}">
    <title>Chạy thử trên máy – mở site bằng tên miền</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; max-width: 46rem; margin: 3rem auto; padding: 0 1rem; color: #111827; line-height: 1.6; }
        h1 { color: #c1272d; font-size: 1.6rem; }
        a { color: #c1272d; font-weight: 600; }
        code, pre { background: #f5f6f8; border-radius: .375rem; padding: .1rem .35rem; }
        pre { padding: .75rem 1rem; overflow-x: auto; }
        li { margin: .4rem 0; }
        .note { background: #fff7e6; border-left: 4px solid #f59e0b; padding: .75rem 1rem; }
    </style>
</head>
<body>
    <h1>Đây là địa chỉ IP, không phải tên miền của site</h1>
    <p>Hệ thống nhận diện site theo <strong>tên miền</strong>, nên mở bằng IP/localhost sẽ không biết là site nào.
        Trang này chỉ hiện khi chạy thử trên máy (<code>APP_ENV=local</code>); trên máy chủ thật, địa chỉ này trả 404.</p>

    <h2>Mở site</h2>
    @if ($sites->isEmpty())
        <p>Chưa có site nào đang hoạt động. Chạy: <code>php artisan db:seed --class=Database\Seeders\DemoSiteSeeder</code></p>
    @else
        <ul>
            @foreach ($sites as $site)
                <li><a href="{{ $origin($site->domain) }}/">{{ $site->name }}</a> – <code>{{ $origin($site->domain) }}/</code></li>
            @endforeach
        </ul>
    @endif

    <h2>Trang quản trị</h2>
    <p><a href="{{ $adminUrl }}">{{ $adminUrl }}</a></p>

    <h2>Nếu bấm vào mà trình duyệt báo không tìm thấy máy chủ</h2>
    <div class="note">
        <p>Thêm các dòng sau vào file <strong>hosts</strong> (Windows: <code>C:\Windows\System32\drivers\etc\hosts</code>, mở Notepad bằng quyền Administrator), lưu lại rồi tải lại trang:</p>
        <pre>@foreach ($sites as $site)127.0.0.1 {{ $site->domain }}
@endforeach{{ '127.0.0.1 '.$adminDomain }}</pre>
    </div>
</body>
</html>
