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

Site được nhận diện theo tên miền. Để mở site mẫu trên máy, trỏ `vpptrangthietbi.com.vn`
về `127.0.0.1` trong file hosts rồi vào `http://vpptrangthietbi.com.vn:8000/kien-thuc/cach-chon-giay-in-a4`.

Kiểm tra:

- `php artisan test`: test tự động (cần database `pvt_test`, xem `phpunit.xml`).
- `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`.
- `node tests/Browser/no-javascript.mjs`: tắt JavaScript, nội dung vẫn phải hiện đủ (cần app đang chạy).

## Cấu trúc

- `docs/grilling-log.md`: câu hỏi, đề xuất và quyết định qua từng vòng.
- `.claude/skills/`: bộ skill dùng cho Claude Code (nguồn: https://github.com/mattpocock/skills).
- `skills-lock.json`: phiên bản skill đã cài.
