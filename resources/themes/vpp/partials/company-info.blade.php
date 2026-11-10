{{-- Thông tin pháp lý và liên hệ của D&T: một nguồn (CompanyProfile) cho mọi site. --}}
@php($company = \App\Models\CompanyProfile::current())
@php($address = implode(', ', array_filter([$company->value('street_address'), $company->value('locality'), $company->value('region')])))
<section class="company-info">
    <h2>Thông tin công ty</h2>
    <p><strong>{{ $company->value('legal_name') }}</strong></p>
    <ul>
        @if ($company->value('tax_id'))
            <li>Mã số thuế: {{ $company->value('tax_id') }}</li>
        @endif
        @if ($address !== '')
            <li>Địa chỉ: {{ $address }}</li>
        @endif
        @if ($company->value('phone'))
            <li>Điện thoại: <a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $company->value('phone')) }}">{{ $company->value('phone') }}</a></li>
        @endif
        @if ($company->value('email'))
            <li>Email: <a href="mailto:{{ $company->value('email') }}">{{ $company->value('email') }}</a></li>
        @endif
        <li>Website chính: {{ $company->host() }}</li>
    </ul>
</section>
