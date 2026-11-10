@extends('theme::layout')

@section('title', 'Yêu cầu báo giá')

@section('meta_description', 'Gửi yêu cầu báo giá theo số lượng cho doanh nghiệp, nhà máy. Phản hồi trong giờ làm việc.')

@section('content')
    @include('theme::partials.breadcrumb', ['trail' => [['Yêu cầu báo giá', null]]])

    <h1>Yêu cầu báo giá theo số lượng</h1>
    <p>Điền thông tin, bộ phận kinh doanh sẽ liên hệ báo giá phù hợp số lượng và điều kiện giao hàng của doanh nghiệp.</p>

    @include('theme::partials.quote-form', ['product' => $product])
@endsection
