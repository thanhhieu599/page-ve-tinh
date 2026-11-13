# CRM bán hàng chung mọi site

Địa chỉ: `https://<ADMIN_DOMAIN>/crm` (cùng tài khoản với trang quản trị nội dung `/admin`).
Một CRM cho **mọi site vệ tinh**: không chọn site, mọi danh sách gom dữ liệu của tất cả site, lọc theo "Site nguồn".

## Vai trò

| Vai trò | Vào được | Thấy dữ liệu |
|---|---|---|
| Admin | `/admin` và `/crm` | Tất cả |
| Trưởng phòng kinh doanh | `/crm` | Tất cả; giao lead; xuất MISA; cài đặt CRM |
| Nhân viên kinh doanh | `/crm` | Chỉ lead/khách/báo giá/đơn/việc được giao cho mình |
| Biên tập, Người viết | `/admin` | Không vào CRM |

Tạo tài khoản và gán vai trò: Admin → `/admin` → **Hệ thống → Người dùng** (trong CRM: Cấu hình → Người dùng và phân quyền).

## Menu

- **Tổng quan**: doanh số tháng, báo giá đang mở, lead mới, công nợ quá hạn, việc hôm nay; doanh số 12 tháng; việc của tôi; lead chưa giao.
- **Bán hàng**
  - *Đơn hàng*: dòng hàng, VAT, số hoá đơn, hạn thanh toán, ghi nhận tiền thu (tự tính "Chưa/Một phần/Đã thanh toán"), lọc **Công nợ quá hạn**. "Đã giao xong" → tính ngày nhắc tái đặt hàng.
  - *Khách hàng tiềm năng*: lead từ form mọi site + nhập tay (điện thoại, Zalo, email, hội chợ, giới thiệu). Thao tác: Giao cho (có thông báo), Chuyển thành khách hàng, Tạo báo giá, Ghi hoạt động.
  - *Khách hàng*: doanh nghiệp (MST, FDI/nội địa, ngành, KCN, tên miền email), người liên hệ (Mua hàng, QA/EHS, Hành chính/Kho…), báo giá, đơn hàng, hoạt động, việc, yêu cầu từ các site; doanh số và công nợ từng khách.
  - *Báo giá*: số BG-năm-0001, dòng hàng (chọn sản phẩm mọi site tự điền tên/mã/ĐVT/giá), VAT, hiệu lực, điều kiện; **In / Lưu PDF** (A4, số tiền bằng chữ); Đã gửi → Đàm phán → **Thắng (tạo đơn DH-năm-0001)** / Thua (ghi lý do).
  - *Việc cần làm*: gọi lại, gửi báo giá, theo dõi, chăm sóc, nhắc tái đặt hàng, thu nợ; tab Hôm nay & quá hạn.
  - *Chăm sóc & tái đặt hàng*: khách đến kỳ mua lại; "Báo giá lại theo đơn gần nhất" một bấm.
- **Sản phẩm**: sản phẩm của mọi site (gán thương hiệu, đơn vị tính); thương hiệu. Nội dung sản phẩm vẫn sửa ở `/admin` từng site.
- **Nội dung website**: mỗi site đang hoạt động một mục (vd. *Văn phòng phẩm D&T*): Tổng quan site, Bài viết, Sản phẩm, Danh mục, Banner & cài đặt site, Giới thiệu/chính sách, Xem website; số bên cạnh = lead mới chưa giao của site. Site mới tự hiện. Chỉ người có quyền quản trị nội dung site đó thấy.
- **Phân tích**: Báo cáo (theo site, theo nhân viên, phễu báo giá, lý do thua); Nhật ký thông báo; Xuất Excel MISA (chứng từ bán hàng, danh mục khách hàng).
- **Cấu hình**: Cài đặt CRM (VAT, hiệu lực báo giá, điều kiện giao/thanh toán, tài khoản ngân hàng, hạn thanh toán, chu kỳ tái đặt hàng); Người dùng.

## Tự động

- Lead mới (mọi site) **tự gắn vào khách hàng đã có** khi trùng email/SĐT người liên hệ hoặc tên miền email công ty (không dùng gmail, yahoo…). Khách đã có người phụ trách → lead về đúng người đó.
- Hằng ngày 07:40: `php artisan crm:nhac-tai-dat-hang` tạo việc "Nhắc tái đặt hàng" (9:00 sáng ngày đến kỳ) cho khách đến kỳ trong 7 ngày, không tạo trùng.
- Lead nhập tay trong CRM vẫn đồng bộ Mautic (tag `crm-<kênh>`).

## Không làm trong CRM (có hệ thống khác lo)

Kho, nhập hàng, nhà cung cấp (Sapo); kế toán (MISA, nhập qua file Excel xuất từ CRM); mã giảm giá, đánh giá sản phẩm (không hợp bán B2B theo báo giá).

## Dữ liệu mẫu (máy local)

`php artisan db:seed --class=Database\Seeders\CrmDemoSeeder` (tên có chữ "mẫu", không tạo tài khoản).
