# Nhật ký chất vấn – Page vệ tinh

Ghi lại từng vòng hỏi: câu hỏi, phương án đề xuất, và quyết định của anh Thụy.
Trạng thái: `CHỜ` (chưa trả lời) · `CHỐT` (đã quyết) · `ĐỔI` (chốt khác đề xuất).

## Rủi ro nền cần nhớ

Google coi các page na ná nhau, lập ra chỉ để kéo truy cập về site chính, là **doorway pages**.
Sản xuất nội dung hàng loạt chất lượng thấp là **scaled content abuse**. Mạng domain vệ tinh trỏ
link về site chính có thể bị coi là **link spam (PBN)**. Vi phạm có thể kéo tụt cả trangthietbi.vn.
=> Mỗi page phải có giá trị riêng đo được (tiêu chuẩn, case study, dữ liệu riêng), và database
phải theo dõi được điều đó.

## Vòng 1 – Nền móng

| # | Câu hỏi | Đề xuất | Trạng thái |
|---|---|---|---|
| # | Câu hỏi | Đề xuất | Quyết định | Trạng thái |
|---|---|---|---|---|
| Q1 | "Vệ tinh" nằm ở đâu | Kết hợp (C) trụ cột + (A) cho 1–2 thương hiệu | **(A) Domain riêng cho từng ngành** | ĐỔI |
| Q2 | Chỉ số chính | Số RFQ/tháng | **Cả 3: traffic/thứ hạng, RFQ, doanh thu** | ĐỔI |
| Q3 | Ngành & thứ tự | TOWA, PPE điện tử, thực phẩm, thép | **Văn phòng phẩm → PPE → Tool (MRO) → TOWA** | ĐỔI |
| Q4 | Ai vận hành | Marketing + AI, anh duyệt | **"dev"** – làm rõ ở Q6 | CHỐT (xem Q6) |
| Q5 | Database quản lý gì | 4 lớp | **Theo thiết kế đa site của anh** – xem `database-schema.md` | CHỐT |

Hệ quả: đây là một **hệ thống CMS đa site tự xây** (một database, nhiều domain), không phải
chỉ là file theo dõi page.

## Vòng 2 – Kiến trúc & phạm vi

| # | Câu hỏi | Quyết định | Trạng thái |
|---|---|---|---|
| Q6 | Ai xây, ai vận hành | (c) Claude viết code; dev lo server/deploy; marketing nhập bài | CHỐT |
| Q7 | Nền tảng | PHP 8.4+, Laravel 13, MySQL 8/MariaDB 10.11+, Redis, Nginx; SSR Blade bắt buộc; đa site qua Host + global scope; theme riêng mỗi site; dùng thư viện Spatie… – xem `tech-stack.md` | CHỐT (anh tự đặt) |
| Q8 | Hạ tầng | **Mỗi domain một hosting riêng** – mâu thuẫn với "một database" ở Q7, xử lý ở Q14 | MỞ |
| Q9 | Bảng đo 3 chỉ số | Thêm `users`, `gsc_page_daily`, cột status/deal_value/won_at cho `leads` | CHỐT |
| Q10 | Bảng độ khác biệt | Thêm `standards`, `post_standard`, `products` | CHỐT |
| Q11 | Chính sách liên kết | 4 quy tắc như đề xuất, code tự áp | CHỐT |
| Q12 | Ngôn ngữ | Tiếng Việt trước, có cột `locale` từ đầu | CHỐT |
| Q13 | Mautic | Đã có Mautic; DB CMS tách riêng, trao đổi qua API. Domain: chưa trả lời (hỏi lại ở Q18) | CHỐT một phần |

## Vòng 3 – Hạ tầng, tích hợp, vận hành

| # | Câu hỏi | Quyết định | Trạng thái |
|---|---|---|---|
| Q14 | Một DB vs mỗi domain một hosting | **(a)** Một server ứng dụng + một DB; mọi domain trỏ về cùng app. Q8 đóng theo hướng này | CHỐT |
| Q15 | Đẩy lead sang Mautic | Lưu `leads` trước → job Redis gọi Mautic REST API (tạo/cập nhật contact, tag site/ngành, UTM) → ghi `synced_to_mautic_at`; lỗi tự thử lại, quá 5 lần báo email dev | CHỐT |
| Q16 | Cấu trúc URL | Chữ thường, không dấu, gạch ngang, < 75 ký tự; không "/" cuối, không `.html`; 301 mọi biến thể (có/không "/", chữ hoa, http→https, www↔không www). Danh mục `/danh-muc/{slug}`, SP `/san-pham/{slug}`, tiêu chuẩn `/tieu-chuan/{slug}`, tác giả `/tac-gia/{slug}`, tiếng Anh `/en/…`. **Bài viết: `/{slug}` hay `/kienthuc/{slug}` – vênh, chốt ở Q22** | CHỐT một phần |
| Q17 | Duyệt bài & phân quyền | Nháp → Chờ duyệt → Đã đăng (+ hẹn giờ); Admin / Biên tập / Người viết; quyền theo site; nội dung PPE bắt buộc qua người duyệt có chuyên môn | CHỐT |
| Q18 | Domain site VPP | **vpptrangthietbi.com.vn** | CHỐT |
| Q19 | Môi trường & CI | Staging + production; GitHub Actions: test, Pint, PHPStan, test "tắt JS"; vào `main` qua PR; dev bấm deploy production | CHỐT |
| Q20 | Sao lưu | spatie/laravel-backup hằng ngày ra kho ngoài server; giữ 30 ngày + 12 tháng; thử khôi phục mỗi quý | CHỐT |
| Q21 | Form lead | Honeypot + Turnstile + giới hạn tần suất; ô đồng ý không tick sẵn; thêm cột `consent_text`, `ip` vào `leads` | CHỐT |

## Vòng 4 – Phạm vi bản đầu (site VPP) & cách làm việc

| # | Câu hỏi | Đề xuất | Trạng thái |
|---|---|---|---|
| Q22 | Tiền tố URL bài viết | `/kien-thuc/{slug}` (đúng quy tắc gạch ngang) | CHỜ |
| Q23 | www hay không www | Không www: `https://vpptrangthietbi.com.vn` | CHỜ |
| Q24 | Nguồn dữ liệu sản phẩm | Nhập CSV xuất từ Sapo; `buy_url` trỏ về trang SP trên trangthietbi.vn; không đồng bộ giá/tồn kho ở bản đầu | CHỜ |
| Q25 | Trang quản trị | Filament (Laravel, Livewire) + plugin TipTap | CHỜ |
| Q26 | Giao diện & tốc độ | Theme Blade + Tailwind; mục tiêu Core Web Vitals: LCP < 2.5s, CLS < 0.1, INP < 200ms | CHỜ |
| Q27 | Điều kiện mở index | `noindex` đến khi có ≥ 20 bài đạt chuẩn + trang công ty/tác giả/chính sách | CHỜ |
| Q28 | Đo lường | GA4 + Search Console mỗi site; sự kiện GA4 khi gửi form; UTM giữ đến khi tạo lead | CHỜ |
| Q29 | Nơi quản lý đầu việc | GitHub Issues của repo `page-ve-tinh` | CHỜ |
| Q30 | Phạm vi bản đầu (MVP) | Lõi đa site + site VPP: trang chủ, danh mục, bài, SP, tác giả, form RFQ, sitemap, schema, redirect, nhập GSC, đồng bộ Mautic | CHỜ |
