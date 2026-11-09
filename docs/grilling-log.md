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
| Q4 | Ai vận hành | Marketing + AI, anh duyệt | **"dev"** – cần làm rõ (vòng 2, Q6) | MỞ |
| Q5 | Database quản lý gì | 4 lớp | **Theo thiết kế đa site của anh** – xem `database-schema.md` | CHỐT |

Hệ quả: đây là một **hệ thống CMS đa site tự xây** (một database, nhiều domain), không phải
chỉ là file theo dõi page.

## Vòng 2 – Kiến trúc & phạm vi

| # | Câu hỏi | Đề xuất | Trạng thái |
|---|---|---|---|
| Q6 | "dev" nghĩa là ai xây và ai vận hành | Claude viết code trong repo; 1 dev (nội bộ/thuê) deploy & bảo trì server; marketing nhập nội dung qua trang quản trị | CHỜ |
| Q7 | Nền tảng kỹ thuật | Laravel + MySQL 8 + Filament (trang quản trị); render phía server; 1 codebase phục vụ mọi domain | CHỜ |
| Q8 | Hạ tầng | 1 VPS (Singapore hoặc VN) + Cloudflare; mỗi domain trỏ về cùng app | CHỜ |
| Q9 | Bổ sung bảng cho Q2 | Thêm `users`, `gsc_page_daily`, cột trạng thái/giá trị/ngày chốt cho `leads` | CHỜ |
| Q10 | Bổ sung bảng cho độ khác biệt | Thêm `standards`, `post_standard`, `products` (link sang trangthietbi.vn) | CHỜ |
| Q11 | Chính sách liên kết giữa các site | Minh bạch chủ sở hữu D&T; link sang trangthietbi.vn theo ngữ cảnh mua hàng; không link chéo kiểu mạng lưới; cấm anchor từ khoá chính xác ở footer toàn site | CHỜ |
| Q12 | Ngôn ngữ | Tiếng Việt trước; thêm cột `locale` ngay từ đầu để mở tiếng Anh cho FDI sau | CHỜ |
| Q13 | Mautic & domain | Cần biết: Mautic đã chạy chưa; đã mua domain nào | CHỜ |
