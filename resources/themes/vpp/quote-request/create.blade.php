@extends('theme::layout')

@section('title', 'Yêu cầu báo giá')

@section('meta_description', 'Gửi yêu cầu báo giá theo số lượng cho doanh nghiệp, nhà máy. Phản hồi trong giờ làm việc.')

@php($company = \App\Models\CompanyProfile::current())

@section('content')
    @include('theme::partials.breadcrumb', ['trail' => [['Yêu cầu báo giá', null]]])

    <div class="quote-page">
        <div class="quote-page-main">
            <h1>Yêu cầu báo giá theo số lượng</h1>
            <p class="text-muted">Điền thông tin, bộ phận kinh doanh sẽ liên hệ báo giá phù hợp số lượng và điều kiện giao hàng của doanh nghiệp.</p>

            <div class="quote-page-card">
                @include('theme::partials.quote-form', ['product' => $product, 'source' => $source ?? null])
            </div>
        </div>

        <aside class="quote-page-aside" aria-label="Thông tin báo giá">
            <section class="sidebar-box">
                <h2>Quy trình báo giá</h2>
                <ol class="quote-steps">
                    <li><strong>Gửi nhu cầu</strong><span>Tên hàng, quy cách, số lượng, địa điểm giao.</span></li>
                    <li><strong>Nhận báo giá</strong><span>Phản hồi trong giờ làm việc, kèm thời gian giao.</span></li>
                    <li><strong>Xác nhận đơn</strong><span>Giao tận nơi, hoá đơn VAT, chứng từ đầy đủ.</span></li>
                </ol>
            </section>

            <section class="sidebar-box">
                <h2>Cam kết</h2>
                <ul class="product-promises mt-0 border-0 bg-transparent p-0">
                    <li>✓ Giá theo số lượng cho doanh nghiệp</li>
                    <li>✓ Hàng rõ nguồn gốc, đủ chứng từ</li>
                    <li>✓ Giao văn phòng, nhà máy, KCN</li>
                </ul>
            </section>

            @if ($company->value('phone') || $company->value('email'))
                <section class="sidebar-box sidebar-cta">
                    <h2>Cần gấp?</h2>
                    @if ($company->value('phone'))
                        <p>Hotline: <a class="font-bold text-white" href="{{ $company->telHref() }}">{{ $company->value('phone') }}</a></p>
                    @endif
                    @if ($company->value('email'))
                        <p>Email: <a class="font-bold text-white" href="mailto:{{ $company->value('email') }}">{{ $company->value('email') }}</a></p>
                    @endif
                </section>
            @endif
        </aside>
    </div>
@endsection
