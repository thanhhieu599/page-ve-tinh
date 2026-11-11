{{--
    Footer 4 cột (mọi trang): Liên hệ · Danh mục sản phẩm · Chính sách · Vận chuyển & thanh toán,
    rồi dải bản quyền. Theo chính sách liên kết (#14):
    - ghi rõ site thuộc D&T; không link sang site vệ tinh khác hay trangthietbi.vn;
    - danh mục trùng từ khoá chính bị bỏ khỏi footer (SiteMenu::footerCategories).
    Test cho mọi theme: tests/Feature/PublicSite/FooterLinkPolicyTest.php
--}}
@php($company = \App\Models\CompanyProfile::current())
@php($footerCategories = \App\Support\SiteMenu::footerCategories())
@php($policies = \App\Support\SiteMenu::policyPages($site))
<footer class="site-footer">
    <div class="footer-columns">
        <section>
            <h2 class="footer-heading">Liên hệ</h2>
            <ul class="footer-contact">
                <li class="font-semibold text-white">{{ $company->value('legal_name') }}</li>
                @if ($company->value('tax_id'))
                    <li>MST: {{ $company->value('tax_id') }}</li>
                @endif
                @if ($company->address())
                    <li>Trụ sở: {{ $company->address() }}</li>
                @endif
                @foreach ($company->otherAddresses() as $address)
                    <li>{{ $address['label'] !== '' ? $address['label'].': ' : '' }}{{ $address['address'] }}</li>
                @endforeach
                @foreach ($company->contactPeople() as $person)
                    <li>{{ $person['name'] !== '' ? $person['name'].': ' : '' }}<a href="{{ $person['tel'] }}">{{ $person['phone'] }}</a></li>
                @endforeach
                @if ($company->value('phone'))
                    <li>Hotline: <a href="{{ $company->telHref() }}">{{ $company->value('phone') }}</a></li>
                @endif
                @if ($company->value('email'))
                    <li>Email: <a href="mailto:{{ $company->value('email') }}">{{ $company->value('email') }}</a></li>
                @endif
            </ul>
            @if ($socials = $company->socialLinks())
                <p class="footer-socials">
                    @foreach ($socials as $social)
                        <a href="{{ $social['url'] }}" rel="noopener me" target="_blank" class="social-{{ $social['key'] }}"><span aria-hidden="true">{{ ['facebook' => 'f', 'youtube' => '▶', 'zalo' => 'Z', 'tiktok' => '♪', 'linkedin' => 'in'][$social['key']] }}</span><span class="sr-only">{{ $social['label'] }}</span></a>
                    @endforeach
                </p>
            @endif
        </section>

        <section>
            <h2 class="footer-heading">Danh mục sản phẩm</h2>
            <ul class="footer-links">
                @foreach ($footerCategories as $item)
                    <li><a href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
                @endforeach
                <li><a href="{{ \App\Http\Controllers\QuoteRequestController::PATH }}">Yêu cầu báo giá</a></li>
            </ul>
        </section>

        <section>
            <h2 class="footer-heading">Chính sách</h2>
            <ul class="footer-links">
                @foreach ($policies as $item)
                    <li><a href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
                @endforeach
                <li><a href="{{ \App\Http\Controllers\ArticleController::PATH }}">Tin tức</a></li>
            </ul>
        </section>

        <section>
            <h2 class="footer-heading">Vận chuyển &amp; thanh toán</h2>
            <ul class="footer-payments">
                @foreach ($company->paymentMethodList() as $method)
                    <li>{{ $method }}</li>
                @endforeach
            </ul>
            @if ($badge = $company->bctBadge())
                <p class="mt-4">
                    @if ($badge['href'])<a href="{{ $badge['href'] }}" rel="noopener" target="_blank">@endif<img src="{{ $badge['url'] }}" alt="Đã thông báo Bộ Công Thương" width="{{ $badge['width'] }}" height="{{ $badge['height'] }}" loading="lazy">@if ($badge['href'])</a>@endif
                </p>
            @endif
        </section>
    </div>

    <div class="footer-bottom">
        <p>© {{ now()->year }} {{ $company->value('legal_name') }}. {{ $site->name }} là website chuyên đề thuộc {{ $company->value('name') }} ({{ $company->host() }}).</p>
    </div>
</footer>
