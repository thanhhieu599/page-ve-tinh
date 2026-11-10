<?php

return [

    /*
    | Ép mọi URL công khai về https. Tắt ở local và trong job kiểm tra
    | trình duyệt của CI (chạy http thuần), bật trên staging/production.
    */
    /*
    | Domain riêng của trang quản trị. Trang quản trị không chạy trên domain site
    | công khai, để tách cookie đăng nhập và không bị quy tắc URL công khai can thiệp.
    */
    // Thư mục chứa theme; mỗi theme là một thư mục con có layout.blade.php.
    // Thêm site mới chỉ cần thêm thư mục theme, không sửa code lõi.
    'themes_path' => env('SITES_THEMES_PATH', resource_path('themes')),

    // Theme dùng khi thư mục theme của site không còn (tránh lỗi 500 toàn site).
    'default_theme' => env('SITES_DEFAULT_THEME', 'vpp'),

    'admin_domain' => env('ADMIN_DOMAIN', 'admin.localhost'),

    /*
    | Cloudflare Turnstile cho form yêu cầu báo giá. Để trống = tắt (local).
    */
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        // true: bắt buộc qua Turnstile (khách tắt JavaScript không gửi được form).
        // false: không có token thì vẫn nhận, đánh dấu lead "chưa xác minh".
        'required' => (bool) env('TURNSTILE_REQUIRED', false),
    ],

    /*
    | Giới hạn số lần gửi form yêu cầu báo giá trên mỗi IP, mỗi site.
    */
    'quote_rate_limit' => ['attempts' => 5, 'decay_seconds' => 600],

    /* Email nhận cảnh báo kỹ thuật (vd. lead không đồng bộ được sang Mautic). */
    'alert_email' => env('DEV_ALERT_EMAIL'),

    'force_https' => (bool) env('FORCE_HTTPS', true),

    /*
    | Proxy đứng trước app (Nginx/Cloudflare) được tin để đọc X-Forwarded-Proto/For.
    | Danh sách IP cách nhau dấu phẩy, hoặc "*". Để trống = không tin proxy nào.
    | Thiếu cấu hình này khi bật force_https sau proxy có thể gây chuyển hướng lặp.
    */
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

];
