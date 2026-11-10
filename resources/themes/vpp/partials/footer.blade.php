{{--
    Footer lặp trên mọi trang. Theo chính sách liên kết (#14):
    - ghi rõ site thuộc D&T (minh bạch chủ sở hữu, không ẩn danh như mạng lưới vệ tinh);
    - chỉ nhãn điều hướng, không anchor từ khoá; không link sang site vệ tinh khác;
    - không link sang trangthietbi.vn ở footer (chỉ link trong ngữ cảnh mua hàng/báo giá).
    Có test tự động cho mọi theme: tests/Feature/PublicSite/FooterLinkPolicyTest.php
--}}
<footer class="site-footer">
    <p>{{ $site->name }} là website chuyên đề thuộc {{ config('company.legal_name') }} ({{ parse_url((string) config('company.url'), PHP_URL_HOST) }}).</p>
    <nav aria-label="Liên kết cuối trang">
        <a href="{{ \App\Http\Controllers\QuoteRequestController::PATH }}">Yêu cầu báo giá</a>
    </nav>
</footer>
