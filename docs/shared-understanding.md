# Bản tóm tắt thống nhất – Hệ thống page vệ tinh D&T

Kết quả buổi chất vấn 6 vòng (Q1–Q36). Chi tiết từng câu: `grilling-log.md`.
Tiêu chuẩn kỹ thuật: `tech-stack.md`. Thiết kế dữ liệu: `database-schema.md`.

## 1. Mục tiêu

- Mỗi ngành hàng chính có **một domain riêng**, minh bạch thuộc D&T.
- Thứ tự: **Văn phòng phẩm → PPE → Tool (MRO) → TOWA**.
- Đo bằng **cả 3 chỉ số**: traffic/thứ hạng (Search Console), số RFQ, doanh thu từ lead.

## 2. Kiến trúc

- **Một CMS đa site tự xây**: một bộ code, một database, một server ứng dụng; mọi domain trỏ về đó.
- PHP 8.4+, Laravel 13, MySQL 8 / MariaDB 10.11+, Redis, Nginx, Cloudflare.
- Render HTML đầy đủ từ Blade; tắt JavaScript vẫn đọc đủ nội dung.
- Site xác định qua HTTP Host; mọi truy vấn tự lọc theo `site_id` (global scope).
- Theme và cấu hình riêng mỗi site; thêm site mới không sửa code lõi.
- Trang quản trị: Filament + TipTap; HTML lọc qua mews/purifier.
- Database CMS tách riêng database Mautic.

## 3. Nội dung, SEO, liên kết

- URL: chữ thường, không dấu, gạch ngang, < 75 ký tự, không "/" cuối, không `.html`, không www, https.
  301 mọi biến thể về dạng chuẩn. Đổi slug tự tạo redirect.
- `/kien-thuc/{slug}` · `/danh-muc/{slug}` · `/san-pham/{slug}` · `/tieu-chuan/{slug}` · `/tac-gia/{slug}` · tiếng Anh sau thêm `/en/`.
- Sitemap, schema (Article, FAQPage, Product, Organization, BreadcrumbList), trang tác giả (E-E-A-T).
- `keyword_map` chống các site tranh từ khoá của nhau.
- Chính sách liên kết (code tự áp): ghi rõ thuộc D&T; chỉ link sang trangthietbi.vn khi có ngữ cảnh
  mua hàng/báo giá; không link chéo kiểu mạng lưới; không anchor từ khoá chính ở footer toàn site.
- `noindex` đến khi có ≥ 20 bài đạt chuẩn + trang công ty, tác giả, chính sách quyền riêng tư, liên hệ.
- Tiếng Việt trước; cột `locale` có sẵn.

## 4. Quy trình

- Nháp → Chờ duyệt → Đã đăng (+ hẹn giờ). Vai trò Admin / Biên tập / Người viết, gán theo site.
  Nội dung PPE bắt buộc qua người duyệt có chuyên môn.
- Claude viết code + test + migration; dev lo server, deploy, sao lưu; marketing nhập bài.
- Đầu việc trên GitHub Issues của repo này; code vào `main` qua PR.

## 5. Sản phẩm & giá

- Nguồn: CSV từ Sapo + nhập tay (MVP); Sapo API ngay sau MVP.
- Khớp theo SKU. Sapo quyết định tên/SKU/thông số/link mua; CMS quyết định mô tả, ảnh, liên kết bài, FAQ
  (không bị ghi đè). SP ngừng bán → ẩn, giữ dữ liệu, URL 410 hoặc 301 sang SP thay thế.
- Hiển thị giá bán lẻ: "Giá tham khảo, chưa gồm VAT. Cập nhật ngày dd/mm/yyyy."
  Nút chính "Yêu cầu báo giá theo số lượng"; có link "Xem trên trangthietbi.vn".
- Giá quá 14 ngày chưa cập nhật → tự ẩn, hiện "Liên hệ báo giá"; schema chỉ khai giá còn hạn;
  trang quản trị cảnh báo SP sắp hết hạn giá. Không hiển thị tồn kho.

## 6. Lead & đo lường

- Form RFQ: honeypot + Cloudflare Turnstile + giới hạn tần suất; ô đồng ý không tick sẵn;
  lưu `consent_text`, `ip`, `consent_at`.
- Lead lưu DB trước → job Redis gọi Mautic REST API (tag site/ngành, UTM) → `synced_to_mautic_at`;
  lỗi tự thử lại, quá 5 lần báo email dev.
- Lead có `status`, `deal_value`, `won_at` để tính doanh thu.
- GA4 + Search Console riêng mỗi site; sự kiện GA4 khi gửi RFQ; giữ UTM & trang vào đầu tiên;
  kéo Search Console hằng ngày vào `gsc_page_daily`.

## 7. Vận hành

- Staging + production. CI (GitHub Actions): test, Pint, PHPStan, test "tắt JS", kiểm tra Core Web Vitals
  (LCP < 2.5s, CLS < 0.1, INP < 200ms; trong CI đo TBT < 200ms thay INP, vì INP cần người dùng thật tương tác). Dev bấm deploy production.
- Sao lưu hằng ngày ra kho ngoài server; giữ 30 ngày + 12 tháng; thử khôi phục mỗi quý.

## 8. Phạm vi bản đầu (MVP) – site vpptrangthietbi.com.vn

**Có:** lõi đa site; trang quản trị đầy đủ (bài + lịch sử sửa, danh mục, tag, FAQ, tác giả, SP, media,
redirect, keyword map, phân quyền, duyệt bài); site VPP (trang chủ, danh mục, bài, SP, tác giả, trang
công ty & chính sách, form RFQ); SEO đầy đủ; Mautic + Search Console; nhập SP từ CSV; CI, staging, sao lưu.

**Để sau:** Sapo API (ngay sau MVP); trang tiêu chuẩn (khi làm PPE); báo cáo doanh thu theo bài; tiếng Anh.
