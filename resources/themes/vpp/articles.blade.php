@extends('theme::layout')

@section('title', 'Kiến thức chọn mua')

@section('canonical', \App\Support\CanonicalUrl::for($site, request(), withPage: true))

@section('meta_description', 'Hướng dẫn chọn mua, tiêu chuẩn và kinh nghiệm sử dụng cho bộ phận mua hàng, EHS và quản lý kho.')

@section('content')
    @include('theme::partials.breadcrumb', ['trail' => [['Kiến thức', null]]])

    <h1>Kiến thức chọn mua</h1>
    <div class="mt-6">@include('theme::partials.post-list', ['posts' => $posts])</div>
@endsection
