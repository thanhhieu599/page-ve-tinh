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
    'admin_domain' => env('ADMIN_DOMAIN', 'admin.localhost'),

    'force_https' => (bool) env('FORCE_HTTPS', true),

    /*
    | Proxy đứng trước app (Nginx/Cloudflare) được tin để đọc X-Forwarded-Proto/For.
    | Danh sách IP cách nhau dấu phẩy, hoặc "*". Để trống = không tin proxy nào.
    | Thiếu cấu hình này khi bật force_https sau proxy có thể gây chuyển hướng lặp.
    */
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

];
