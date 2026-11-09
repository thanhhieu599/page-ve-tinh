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

| # | Câu hỏi | Đề xuất | Trạng thái |
|---|---|---|---|
| Q14 | Giải mâu thuẫn Q7 (một DB) vs Q8 (mỗi domain một hosting) | Một server ứng dụng + một DB; mỗi domain trỏ về cùng app | CHỜ |
| Q15 | Đẩy lead sang Mautic | Lưu DB trước → job hàng đợi Redis gọi Mautic API, tự thử lại; Mautic là bản sao | CHỜ |
| Q16 | Cấu trúc URL | Bài `/{slug}`; danh mục `/danh-muc/{slug}`; SP `/san-pham/{slug}`; tiêu chuẩn `/tieu-chuan/{slug}`; tác giả `/tac-gia/{slug}`; tiếng Anh sau thêm `/en/` | CHỜ |
| Q17 | Quy trình duyệt & phân quyền | Nháp → Chờ duyệt → Đã đăng; vai trò Admin / Biên tập / Người viết; quyền gán theo từng site | CHỜ |
| Q18 | Domain site văn phòng phẩm | Cần tên domain (đã mua hay chưa) | CHỜ |
| Q19 | Môi trường & kiểm soát chất lượng | Staging + production; GitHub Actions chạy test, Pint, PHPStan, kiểm tra "tắt JS"; merge qua PR | CHỜ |
| Q20 | Sao lưu | spatie/laravel-backup hằng ngày ra kho ngoài server; giữ 30 ngày; thử khôi phục mỗi quý | CHỜ |
| Q21 | Form lead: chống spam & đồng ý dữ liệu | Honeypot + Cloudflare Turnstile + giới hạn tần suất; ô đồng ý không tick sẵn; lưu nội dung câu đồng ý + IP + thời điểm | CHỜ |
