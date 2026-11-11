# Page vệ tinh – D&T (trangthietbi.vn)

Hệ thống page vệ tinh cho các ngành hàng chính của CTY TNHH TMDV Phát Triển Kỹ Thuật D & T
(PPE, MRO, vật tư công nghiệp, văn phòng phẩm, quà tặng doanh nghiệp), kèm database quản lý
các page đó.

## Mục tiêu

- Mỗi ngành hàng chính có page riêng, đủ sâu về tiêu chuẩn (EHS/PPE) để Google và AI trích dẫn.
- Mỗi page sinh lead/RFQ từ khách B2B, ưu tiên nhà máy FDI.
- Database theo dõi từng page: từ khoá, nội dung, độ khác biệt, hiệu quả.

## Trạng thái

Đặc tả MVP: issue #1. Đầu việc: issue #2–#17 (GitHub Issues).

## Chạy local

Yêu cầu: PHP 8.4+, Composer, MySQL 8 hoặc MariaDB 10.11+, Node 22.

```bash
composer install
cp .env.example .env && php artisan key:generate   # local http thuần: đặt FORCE_HTTPS=false trong .env
php artisan migrate --seed --seeder='Database\Seeders\DemoSiteSeeder'
php artisan serve
```

Site được nhận diện theo tên miền: mở bằng `http://127.0.0.1:8000` sẽ chỉ thấy trang hướng dẫn (khi `APP_ENV=local`), không phải site. Để mở site mẫu trên máy, trỏ `vpptrangthietbi.com.vn`
về `127.0.0.1` trong file hosts rồi vào `http://vpptrangthietbi.com.vn:8000/`.

Trang quản trị chạy trên domain riêng (`ADMIN_DOMAIN` trong `.env`, ví dụ `admin.trangthietbi.test`
khi chạy local, trỏ về `127.0.0.1` trong file hosts giống site). Tạo tài khoản Admin đầu tiên:

```bash
php artisan admin:create email@congty.vn --name="Họ tên"
```

Rồi vào `http://<ADMIN_DOMAIN>:8000/admin`.

Sau mỗi lần kéo code mới có migration: `php artisan migrate`. Bản có trang bắt buộc (#15) cần chạy thêm
một lần `php artisan sites:ensure-pages` để site tạo từ trước có trang giới thiệu, chính sách, liên hệ (nháp).
Thông tin công ty D&T sửa trong trang quản trị, mục "Thông tin công ty" (chỉ Admin).

Lịch chạy tự động (kéo Search Console hằng ngày) cần cron `php artisan schedule:run` mỗi phút trên máy chủ.
Cài Search Console: `docs/search-console.md`.
Dựng máy chủ staging/production, deploy, Cloudflare, sao lưu và khôi phục: `docs/van-hanh.md`.

Hàng đợi (đẩy lead sang Mautic): production dùng `QUEUE_CONNECTION=redis` và chạy liên tục
`php artisan queue:work --tries=5` (supervisor/systemd). Local không cấu hình Mautic thì không cần.

Kiểm tra:

- `php artisan test`: test tự động (cần database `pvt_test`, xem `phpunit.xml`).
- `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`.
- `node tests/Browser/no-javascript.mjs`: tắt JavaScript, nội dung vẫn phải hiện đủ (cần app đang chạy).
- `node tests/Browser/core-web-vitals.mjs`: đo LCP/CLS/TBT trên giả lập điện thoại cho mọi loại trang; vượt ngưỡng thì báo lỗi (cần app đang chạy, đã `npm run build`).

### Lỗi "tải lên không thành công" trong trang quản trị

Chạy `php artisan he-thong:kiem-tra`: lệnh liệt kê mục nào trên máy còn thiếu và cách sửa. Hay gặp trên Windows:

- `extension=fileinfo` còn dấu `;` trong `php.ini` (đường dẫn `php.ini` in ở cuối lệnh).
- Chưa có thư mục tạm upload: tạo `C:\php\tmp`, đặt `upload_tmp_dir="C:\php\tmp"` trong `php.ini`.
- Giới hạn dung lượng: `upload_max_filesize=20M`, `post_max_size=25M`.

Sửa `php.ini` xong phải tắt (Ctrl+C) và chạy lại `php artisan serve`.

Lệnh còn thử lưu một ảnh tạm đúng như khi tải lên, và in **lý do của lần tải lên lỗi gần nhất**
(ứng dụng tự ghi vào `storage/logs/last-upload-failure.json` mỗi khi tải tệp lên thất bại).
Cách nhanh nhất: tải ảnh trong quản trị cho tới khi báo lỗi, rồi chạy `php artisan he-thong:kiem-tra`.

## Cấu trúc

- `docs/grilling-log.md`: câu hỏi, đề xuất và quyết định qua từng vòng.
- `.claude/skills/`: bộ skill dùng cho Claude Code (nguồn: https://github.com/mattpocock/skills).
- `skills-lock.json`: phiên bản skill đã cài.
