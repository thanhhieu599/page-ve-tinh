{{--
    Footer lặp trên mọi trang. Theo chính sách liên kết (#14):
    - ghi rõ site thuộc D&T (minh bạch chủ sở hữu, không ẩn danh như mạng lưới vệ tinh);
    - chỉ nhãn điều hướng, không anchor từ khoá; không link sang site vệ tinh khác;
    - không link sang trangthietbi.vn ở footer (chỉ link trong ngữ cảnh mua hàng/báo giá).
    Có test tự động cho mọi theme: tests/Feature/PublicSite/FooterLinkPolicyTest.php
--}}
<footer class="site-footer">
    @php($company = \App\Models\CompanyProfile::current())
    <p>{{ $site->name }} là website chuyên đề thuộc {{ $company->value('legal_name') }} ({{ $company->host() }}).</p>
    <nav aria-label="Liên kết cuối trang">
        <a href="{{ \App\Http\Controllers\QuoteRequestController::PATH }}">Yêu cầu báo giá</a>
        @foreach (\App\Models\Page::query()->published()->get() as $requiredPage)
            <a href="{{ $requiredPage->path() }}">{{ $requiredPage->kind->getLabel() }}</a>
        @endforeach
    </nav>
</footer>
