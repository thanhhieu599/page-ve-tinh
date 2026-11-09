# Tiêu chuẩn kỹ thuật (chốt ở vòng 2, Q6–Q7)

## Phân vai (Q6)

- Claude viết code, test, migration trong repo này.
- 1 dev (nội bộ hoặc thuê) lo server, tên miền, deploy, sao lưu.
- Marketing nhập nội dung qua trang quản trị, không cần biết code.

## Stack bắt buộc

- PHP **8.4+**, Laravel **13** (phát hành 17/3/2026, hỗ trợ bảo mật đến Q1/2028).
- MySQL 8 hoặc MariaDB 10.11+, Redis, Nginx.
- **Không nhận** source dùng PHP ≤ 8.2 hoặc Laravel ≤ 11 (đã hoặc sắp hết hỗ trợ bảo mật).

## Render phía server (bắt buộc)

- Mọi trang nội dung (bài viết, danh mục, sản phẩm, trang tiêu chuẩn) trả về HTML đầy đủ từ Blade.
- React/Vue chỉ dùng cho khối tương tác (bảng tra cứu, công cụ chọn sản phẩm, form nhiều bước)
  và trang quản trị.
- **Tiêu chí kiểm tra:** tắt JavaScript trên trình duyệt, nội dung bài vẫn hiển thị đủ.

## Đa site

- Một bộ code, một database.
- Middleware đọc HTTP `Host` để xác định `site_id`.
- Mọi truy vấn tự lọc theo `site_id` qua global scope; dev không phải tự nhớ thêm điều kiện.
- Mỗi site có thư mục theme riêng `resources/themes/{site}` và cấu hình riêng
  (logo, màu, GA4 ID, Search Console, Mautic form ID).
- Thêm site mới chỉ bằng thao tác trong trang quản trị + tạo theme, không sửa code lõi.

## Thư viện dùng sẵn (không tự viết)

| Việc | Thư viện |
|---|---|
| Sitemap | spatie/laravel-sitemap |
| Schema.org | spatie/schema-org |
| Cache trang | spatie/laravel-responsecache |
| Xử lý ảnh | league/glide hoặc intervention/image |
| Phân quyền | spatie/laravel-permission |
| Sao lưu | spatie/laravel-backup |
| Trình soạn thảo | TipTap |
| Lọc HTML | mews/purifier |

## Chính sách liên kết (Q11) – code phải tự áp

- Mọi site ghi rõ thuộc D&T, cùng thông tin công ty.
- Chỉ link sang trangthietbi.vn khi có ngữ cảnh mua hàng hoặc báo giá.
- Các site vệ tinh không link chéo nhau theo kiểu mạng lưới.
- Không đặt link anchor trùng từ khoá chính ở footer toàn site.

## Ngôn ngữ (Q12)

Tiếng Việt trước. Cột `locale` có sẵn trong `posts`, `categories`, `faqs` từ đầu.

## Mautic (Q13)

Đã có Mautic. Database CMS **tách riêng** khỏi database Mautic; hai bên trao đổi qua API.
