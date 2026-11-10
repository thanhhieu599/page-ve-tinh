@extends('theme::layout')

@section('title', 'Đã nhận yêu cầu báo giá')

@section('robots', 'noindex')

@push('head-scripts')
    @if ($reportConversion && $site->ga4Id())
        <script>gtag('event', 'generate_lead', { form: 'yeu-cau-bao-gia', site: @json($site->domain) });</script>
    @endif
@endpush

@section('content')
    <h1>Đã nhận yêu cầu báo giá</h1>
    <p>Cảm ơn anh/chị. Bộ phận kinh doanh sẽ liên hệ trong giờ làm việc.</p>
    <p><a href="/">Về trang chủ</a></p>
@endsection
