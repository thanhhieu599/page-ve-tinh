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

| # | Câu hỏi | Quyết định | Trạng thái |
|---|---|---|---|
| Q22 | Tiền tố URL bài viết | `/kien-thuc/{slug}`; 75 ký tự tính cả đường dẫn | CHỐT |
| Q23 | www hay không www | Không www: `https://vpptrangthietbi.com.vn` (áp cho mọi site) | CHỐT |
| Q24 | Nguồn dữ liệu sản phẩm | **Cả 3: CSV từ Sapo, Sapo API, nhập tay** – thời điểm & quy tắc ghi đè chốt ở Q31–Q33 | CHỐT một phần |
| Q25 | Trang quản trị | Filament (bản tương thích Laravel 13) + plugin TipTap; nội dung qua mews/purifier | CHỐT |
| Q26 | Giao diện & tốc độ | (a) Claude dựng theme Blade + Tailwind; LCP < 2.5s, CLS < 0.1, INP < 200ms; kiểm tra tự động trong CI | CHỐT |
| Q27 | Điều kiện mở index | `noindex` đến khi có ≥ 20 bài đạt chuẩn + trang công ty, tác giả, chính sách quyền riêng tư, liên hệ; công tắc index theo site | CHỐT |
| Q28 | Đo lường | GA4 + Search Console riêng mỗi site; sự kiện GA4 khi gửi RFQ; giữ UTM & trang vào đầu tiên đến khi tạo lead; kéo GSC hằng ngày | CHỐT |
| Q29 | Nơi quản lý đầu việc | GitHub Issues của repo `page-ve-tinh` | CHỐT |
| Q30 | Phạm vi MVP | Lõi đa site; trang quản trị; site VPP; SEO (sitemap, schema, 301, redirect tự động, công tắc index); Mautic + GSC; CI, staging, sao lưu. Để sau: trang tiêu chuẩn, báo cáo doanh thu theo bài, tiếng Anh | CHỐT |

## Vòng 5 – Dữ liệu sản phẩm (hệ quả của Q24)

| # | Câu hỏi | Quyết định | Trạng thái |
|---|---|---|---|
| Q31 | Sapo API vào MVP hay để sau | (b) MVP: CSV + nhập tay; Sapo API là đầu việc ngay sau MVP, dùng chung quy tắc ghi đè | CHỐT |
| Q32 | Quy tắc ghi đè | Khớp theo SKU; Sapo quyết định tên/SKU/thông số/link mua; mô tả, ảnh, liên kết bài, FAQ do CMS quyết định, không bị ghi đè; SP ngừng bán → ẩn, giữ dữ liệu, URL 410 hoặc 301 sang SP thay thế | CHỐT |
| Q33 | Hiển thị giá | **Hiển thị giá** (khác đề xuất) – chi tiết ở Q34–Q36 | ĐỔI |

## Vòng 6 – Hiển thị giá (hệ quả của Q33)

| # | Câu hỏi | Quyết định | Trạng thái |
|---|---|---|---|
| Q34 | Giá nào, ghi chú ra sao | Giá bán lẻ từ Sapo; ghi "Giá tham khảo, chưa gồm VAT. Cập nhật ngày dd/mm/yyyy."; nút chính "Yêu cầu báo giá theo số lượng" | CHỐT |
| Q35 | Giá cũ | Quá 14 ngày chưa cập nhật → tự ẩn, hiện "Liên hệ báo giá"; schema Product chỉ khai giá khi còn hạn; trang quản trị cảnh báo SP sắp hết hạn giá | CHỐT |
| Q36 | Tồn kho | Không hiển thị | CHỐT |

**Buổi chất vấn kết thúc: mọi nhánh đã có quyết định.** Bản tóm tắt: `docs/shared-understanding.md`.
