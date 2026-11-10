# Vận hành: dựng máy chủ, deploy, Cloudflare, sao lưu

Tài liệu cho dev. Một bộ code, một database phục vụ mọi site vệ tinh và trang quản trị
(`ADMIN_DOMAIN`). Có hai môi trường: **staging** (thử trước) và **production** (chạy thật).

## 1. Máy chủ

| Thành phần | Phiên bản | Ghi chú |
|---|---|---|
| Ubuntu | 24.04 LTS | |
| PHP-FPM | 8.4+ | extension: bcmath, curl, gd, intl, mbstring, mysql, redis, xml, zip, opcache |
| MySQL / MariaDB | 8.0+ / 10.11+ | utf8mb4; cần `mysqldump` (gói client) để sao lưu |
| Redis | 7 | cache, cache toàn trang, hàng đợi, phiên |
| Nginx | 1.24+ | |
| Node | 22 | chỉ để build CSS khi deploy |
| Composer | 2 | |
| Supervisor | | giữ hàng đợi chạy liên tục |

Staging và production là **hai thư mục/hai máy riêng**, hai database riêng, hai bucket sao lưu
riêng. Staging dùng tên miền riêng (ví dụ `staging-vpp.trangthietbi.vn`) và luôn để site ở
trạng thái chưa index; nên đặt thêm mật khẩu Nginx (basic auth) cho staging.

## 2. Cài lần đầu

```bash
git clone <repo> /var/www/page-ve-tinh && cd /var/www/page-ve-tinh
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate   # rồi điền .env theo mục 3
php artisan migrate --force
php artisan storage:link
php artisan sites:ensure-pages
php artisan admin:create email@congty.vn --name="Họ tên"
php artisan optimize
```

Quyền thư mục: user chạy PHP-FPM (thường `www-data`) phải ghi được `storage/` và `bootstrap/cache/`.

## 3. File `.env` production (bắt buộc kiểm tra)

| Biến | Giá trị production |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://<ADMIN_DOMAIN>` |
| `ADMIN_DOMAIN` | ví dụ `quantri.trangthietbi.vn` |
| `FORCE_HTTPS` | `true` |
| `TRUSTED_PROXIES` | dải IP Cloudflare (https://www.cloudflare.com/ips/), phân cách bằng dấu phẩy |
| `DB_*` | database riêng của môi trường |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` | `redis` |
| `RESPONSE_CACHE_ENABLED` / `RESPONSE_CACHE_DRIVER` | `true` / `pages` (kho file riêng; **không** dùng redis: xoá cache trang trên redis sẽ xoá cả database redis) |
| `MAIL_*`, `MAIL_FROM_ADDRESS` | SMTP gửi email cảnh báo |
| `DEV_ALERT_EMAIL` | email dev nhận cảnh báo (Mautic, sao lưu) |
| `MAUTIC_URL` (https), `MAUTIC_USERNAME`, `MAUTIC_PASSWORD` | xem issue #11 |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Cloudflare Turnstile cho form báo giá |
| `GSC_CREDENTIALS_PATH` | file khoá service account, xem `docs/search-console.md` |
| `BACKUP_S3_*`, `BACKUP_ARCHIVE_PASSWORD` | xem mục 8 |

Thông tin công ty, logo, màu, GA4, Search Console từng site: sửa trong trang quản trị
(**Thông tin công ty**, **Site vệ tinh**), không cần sửa `.env`.

## 4. Nginx

Một `server` cho mọi site vệ tinh và trang quản trị (ứng dụng nhận diện site qua tên miền).
**Không** đặt file `robots.txt` tĩnh: ứng dụng tự sinh theo từng site.

```nginx
server {
    listen 443 ssl http2;
    server_name vpptrangthietbi.com.vn www.vpptrangthietbi.com.vn quantri.trangthietbi.vn;
    root /var/www/page-ve-tinh/public;
    index index.php;

    # Chứng chỉ Cloudflare Origin (SSL Full strict)
    ssl_certificate     /etc/ssl/cloudflare/origin.pem;
    ssl_certificate_key /etc/ssl/cloudflare/origin.key;

    # IP thật của khách khi đi qua Cloudflare (dải IP: https://www.cloudflare.com/ips/)
    # set_real_ip_from <mỗi dải IP Cloudflare>;
    real_ip_header CF-Connecting-IP;

    client_max_body_size 20m;   # ảnh tải lên, file CSV sản phẩm
    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml application/xml;

    # CSS đã build có mã băm trong tên: cache lâu
    location /build/ { expires 1y; add_header Cache-Control "public, immutable"; try_files $uri =404; }
    location /storage/ { expires 30d; add_header Cache-Control "public"; try_files $uri =404; }

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known) { deny all; }
}

server {
    listen 80;
    server_name _;
    return 301 https://$host$request_uri;
}
```

Ứng dụng tự chuyển `www`, chữ hoa, "/" cuối, `.html` về URL chuẩn (301); Nginx không cần làm.

## 5. Hàng đợi và lịch chạy

Supervisor, `/etc/supervisor/conf.d/page-ve-tinh.conf`:

```ini
[program:page-ve-tinh-queue]
command=php /var/www/page-ve-tinh/artisan queue:work redis --tries=5 --max-time=3600
user=www-data
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=120
redirect_stderr=true
stdout_logfile=/var/www/page-ve-tinh/storage/logs/queue.log
```

Cron (`crontab -u www-data -e`):

```
* * * * * cd /var/www/page-ve-tinh && php artisan schedule:run >> /dev/null 2>&1
```

Lịch đã có trong code (giờ Việt Nam): kéo Search Console 07:10; dọn sao lưu 01:40;
sao lưu 02:10; kiểm tra sao lưu 08:20; mỗi phút xoá cache trang nếu có bài hẹn giờ vừa tới giờ đăng. Xem bằng `php artisan schedule:list`.

## 6. Deploy

Chạy trên staging trước, kiểm tra, rồi mới chạy production.

```bash
cd /var/www/page-ve-tinh
php artisan down --retry=30
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan responsecache:clear     # giao diện/nội dung mới hiện ngay
php artisan queue:restart           # worker nạp code mới
php artisan up
```

Sau deploy: mở trang chủ, một bài viết, trang quản trị; gửi thử form báo giá trên staging.
CI (GitHub Actions) phải xanh trước khi merge: test, Pint, PHPStan, kiểm tra tắt JavaScript,
đo Core Web Vitals.

## 7. Cloudflare

- DNS: mọi tên miền site vệ tinh và tên miền quản trị bật **proxy** (đám mây cam).
- SSL/TLS: **Full (strict)**, dùng Origin Certificate trên Nginx; bật **Always Use HTTPS**.
- Cache: để mặc định (Cloudflare chỉ cache CSS/ảnh). **Không** bật "Cache Everything" cho HTML:
  ứng dụng đã cache toàn trang và tự xoá khi biên tập lưu bài; cache HTML ở Cloudflare sẽ làm
  khách thấy nội dung cũ và làm hỏng form báo giá.
- Cache Rule: **Bypass cache** cho tên miền quản trị.
- Bảo vệ trang quản trị: WAF rule chỉ cho phép IP văn phòng/VPN, hoặc Cloudflare Access.
- Turnstile: tạo widget cho các tên miền site, điền khoá vào `.env`.
- Thêm site mới: thêm DNS (proxy) → thêm `server_name` Nginx → thêm site trong trang quản trị.

## 8. Sao lưu và khôi phục

**Thiết lập (một lần):** tạo bucket riêng cho từng môi trường, ví dụ Cloudflare R2
`dt-page-ve-tinh-backup`. Tạo API token chỉ có quyền đọc/ghi bucket đó. Điền `.env`:

```
BACKUP_S3_KEY=...            # Access Key ID
BACKUP_S3_SECRET=...         # Secret Access Key
BACKUP_S3_REGION=auto
BACKUP_S3_BUCKET=dt-page-ve-tinh-backup
BACKUP_S3_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
BACKUP_ARCHIVE_PASSWORD=...  # mật khẩu dài, lưu ở trình quản lý mật khẩu của công ty
```

Chạy thử: `php artisan backup:run` rồi `php artisan backup:list`.

Mỗi bản gồm database và ảnh tải lên, mã hoá AES-256. Giữ 30 bản hằng ngày + 12 bản hằng
tháng. `.env` không nằm trong bản sao lưu: lưu bản `.env` production ở trình quản lý mật khẩu.
**Mất `BACKUP_ARCHIVE_PASSWORD` là không mở được bản sao lưu.**

**Khôi phục:**

1. Tải file `.zip` mới nhất từ bucket. Giải nén bằng 7-Zip (hỗ trợ AES) với mật khẩu sao lưu.
2. Database: `mysql -u <user> -p <database> < db-dumps/mysql-<database>.sql`
3. Ảnh: chép thư mục `public/` trong file zip vào `storage/app/public/`.
4. `php artisan optimize && php artisan responsecache:clear`, kiểm tra trang và trang quản trị.

**Thử khôi phục mỗi quý** (tháng 1, 4, 7, 10). Khôi phục bản mới nhất vào **staging**, rồi kiểm tra:

- [ ] Giải nén được bằng mật khẩu đang lưu.
- [ ] Số bài viết, sản phẩm, lead khớp production (đếm trong trang quản trị).
- [ ] Mở được 3 bài ngẫu nhiên, ảnh hiện đủ.
- [ ] Ghi kết quả (ngày, người làm, thời gian khôi phục, vấn đề) vào nhật ký vận hành.

## 9. Theo dõi hằng ngày

- Email cảnh báo (`DEV_ALERT_EMAIL`): lead không đồng bộ được Mautic; sao lưu lỗi hoặc quá 1 ngày.
- `php artisan queue:failed`: job lỗi (Mautic, Search Console).
- `storage/logs/laravel.log`: lỗi ứng dụng.
- Trang quản trị → **Báo cáo**: số liệu Search Console phải cập nhật mỗi ngày.

## 10. Trước khi mở index cho một site

Trang quản trị → **Mở index (SEO)**: checklist phải đạt hết (≥ 20 bài, hồ sơ tác giả, 3 trang
bắt buộc đã hoàn thiện). Thêm vào đó:

- [ ] Search Console: xác minh tên miền, điền property, chạy `php artisan search-console:pull --days=480`.
- [ ] GA4 Measurement ID đã điền, kiểm tra sự kiện `generate_lead` khi gửi form.
- [ ] Turnstile bật, Mautic nhận lead thử.
- [ ] Sao lưu đã chạy ít nhất một lần thành công.
- [ ] CI xanh (gồm Core Web Vitals).
