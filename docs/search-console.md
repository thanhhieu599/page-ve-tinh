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
3. Lần đầu kết nối: lấy lại lịch sử (Search Console giữ khoảng 16 tháng), không thì báo cáo
   28 ngày sẽ thiếu số liệu trong vài tuần đầu:
   `php artisan search-console:pull --days=480`
   Sau đó lịch hằng ngày tự kéo 7 ngày gần nhất.

## Cách tính

- Mỗi lần chạy kéo 7 ngày gần nhất có số liệu (Google trễ khoảng 2 ngày, ngày tính theo giờ
  Thái Bình Dương), gồm cả số liệu tạm (`dataState=all`), và ghi đè theo (site, ngày) và
  (site, trang, ngày): chạy lại không nhân đôi, số tạm được thay bằng số chốt ở lần sau.
- Hai loại số liệu: **tổng property** theo ngày (`gsc_site_daily`, khớp màn hình Search Console)
  và **theo trang** (`gsc_page_daily`, cho bảng top trang). Cộng các trang lại sẽ ra số lớn hơn
  tổng property, vì một lượt tìm có thể hiện nhiều trang của site.
- Báo cáo: CTR = tổng lượt nhấp / tổng lượt hiển thị; vị trí trung bình có trọng số theo lượt
  hiển thị (như Search Console). Lead tính theo ngày gửi form, doanh thu theo ngày chốt (Thắng),
  giờ Việt Nam.
- Lỗi: thiếu file khoá → log cảnh báo; lỗi sau 3 lần thử (vd. 403 khi service account chưa được
  thêm vào property) → log lỗi và nằm trong `failed_jobs`.
- Admin xem mọi site; Biên tập xem site được gán; Người viết không xem.
