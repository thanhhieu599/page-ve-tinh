# Kéo số liệu Google Search Console

CMS tự kéo số liệu Search Console theo trang của từng site mỗi ngày lúc 07:10 (giờ Việt Nam)
vào bảng `gsc_page_daily`. Ban giám đốc xem ở trang quản trị, mục **Báo cáo**, không cần
đăng nhập Google.

## Cài đặt một lần (dev)

1. Google Cloud Console → tạo (hoặc chọn) một project → bật **Google Search Console API**.
2. IAM & Admin → Service Accounts → tạo service account (ví dụ `bao-cao-page-ve-tinh`).
   Không cần cấp role nào trong project.
3. Mở service account → Keys → Add key → JSON. Tải file về.
4. Đặt file trên máy chủ **ngoài thư mục web**, ví dụ `/etc/page-ve-tinh/gsc.json`,
   quyền đọc chỉ cho user chạy PHP (`chmod 600`). Không đưa file vào Git.
5. Trong `.env`: `GSC_CREDENTIALS_PATH=/etc/page-ve-tinh/gsc.json`.
6. Máy chủ phải có cron chạy lịch của Laravel mỗi phút:
   `* * * * * cd /duong-dan/page-ve-tinh && php artisan schedule:run >> /dev/null 2>&1`
   và hàng đợi đang chạy (`php artisan queue:work`).

## Cho từng site

1. Search Console → property của site (nên dùng **Domain property**, ví dụ `vpptrangthietbi.com.vn`)
   → Settings → Users and permissions → Add user: email của service account
   (dạng `...@....iam.gserviceaccount.com`), quyền **Restricted** (chỉ đọc).
2. Trang quản trị → **Site vệ tinh** → sửa site → ô **Property Search Console**:
   - Domain property: `sc-domain:vpptrangthietbi.com.vn`
   - URL-prefix property: `https://vpptrangthietbi.com.vn/` (có "/" cuối)
3. Chạy thử ngay (không chờ lịch): `php artisan search-console:pull`.

## Cách tính

- Mỗi lần chạy kéo 7 ngày gần nhất có số liệu (Google trễ khoảng 2 ngày) và ghi đè theo
  (site, trang, ngày): chạy lại không nhân đôi, số liệu tạm được cập nhật thành số chốt.
- Báo cáo: CTR = tổng lượt nhấp / tổng lượt hiển thị; vị trí trung bình có trọng số theo lượt
  hiển thị (như Search Console). Lead tính theo ngày gửi form, doanh thu theo ngày chốt (Thắng),
  giờ Việt Nam.
- Admin xem mọi site; Biên tập xem site được gán; Người viết không xem.
