<p><strong>Bản mẫu, cần pháp chế/luật sư duyệt trước khi bật hiển thị.</strong> Chính sách này áp dụng cho website {{ $site->domain }}, thuộc {{ $company->value('legal_name') }}.</p>
<h2>1. Đơn vị xử lý dữ liệu</h2>
<p>{{ $company->value('legal_name') }}@if ($company->value('tax_id')), mã số thuế {{ $company->value('tax_id') }}@endif. Thông tin liên hệ ở cuối chính sách này.</p>
<h2>2. Dữ liệu cá nhân chúng tôi thu thập</h2>
<ul>
<li>Thông tin bạn nhập vào form yêu cầu báo giá: họ tên, công ty, email, số điện thoại, nội dung nhu cầu, sản phẩm quan tâm.</li>
<li>Thông tin ghi nhận tự động khi bạn gửi form: địa chỉ IP, thời điểm gửi, trang bạn gửi form, trang bạn vào đầu tiên và nguồn truy cập (tham số UTM).</li>
<li>Dữ liệu thống kê truy cập qua Google Analytics (cookie phân tích, không dùng để định danh bạn).</li>
</ul>
<h2>3. Mục đích xử lý</h2>
<ul>
<li>Liên hệ tư vấn và gửi báo giá theo yêu cầu của bạn.</li>
<li>Gửi thông tin sản phẩm liên quan đến nhu cầu bạn đã nêu. Bạn có thể từ chối nhận bất cứ lúc nào.</li>
<li>Thống kê để cải thiện nội dung website.</li>
<li>Chống thư rác và gửi form tự động (Cloudflare Turnstile).</li>
</ul>
<h2>4. Cơ sở xử lý</h2>
<p>Chúng tôi chỉ xử lý dữ liệu khi bạn đồng ý, bằng cách tick vào ô đồng ý (không tick sẵn) trước khi gửi form. Nội dung câu đồng ý và thời điểm đồng ý được lưu làm bằng chứng.</p>
<h2>5. Chia sẻ dữ liệu</h2>
<p>Dữ liệu được lưu trên hệ thống do {{ $company->value('name') }} quản lý (website và hệ thống chăm sóc khách hàng Mautic). Chúng tôi dùng dịch vụ của Google (Analytics) và Cloudflare (bảo mật, chống spam). Chúng tôi không bán hay cho thuê dữ liệu cá nhân.</p>
<h2>6. Thời gian lưu trữ</h2>
<p>[CẦN XÁC NHẬN: thời hạn lưu, ví dụ: tối đa 24 tháng kể từ lần liên hệ cuối, trừ khi pháp luật yêu cầu lưu lâu hơn (chứng từ kế toán, hợp đồng).] Hết thời hạn, dữ liệu được xoá hoặc ẩn danh.</p>
<h2>7. Quyền của bạn</h2>
<p>Theo pháp luật Việt Nam về bảo vệ dữ liệu cá nhân, bạn có quyền: được biết về việc xử lý dữ liệu; đồng ý hoặc rút lại sự đồng ý; xem, chỉnh sửa dữ liệu; yêu cầu xoá dữ liệu; hạn chế hoặc phản đối việc xử lý; khiếu nại theo quy định. Gửi yêu cầu qua email bên dưới, chúng tôi phản hồi trong thời hạn luật định.</p>
<h2>8. Bảo vệ dữ liệu</h2>
<p>Website dùng kết nối mã hoá (HTTPS). Chỉ nhân viên được phân quyền mới xem được dữ liệu yêu cầu báo giá.</p>
<h2>9. Liên hệ</h2>
<p>{{ $company->value('legal_name') }}@if ($company->value('email')) – email: {{ $company->value('email') }}@endif @if ($company->value('phone')) – điện thoại: {{ $company->value('phone') }}@endif.</p>
<p>Chính sách có thể được cập nhật; bản mới có hiệu lực từ ngày đăng trên trang này.</p>
