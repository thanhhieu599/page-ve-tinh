@extends('theme::layout')

@section('title', $page->title)

@section('meta_description', (string) $page->meta_description)

@section('content')
    @include('theme::partials.breadcrumb', ['trail' => [[$page->title, null]]])

    <article class="page page-{{ $page->kind->value }}">
        <h1>{{ $page->title }}</h1>

        {{-- Nội dung đã được lọc HTML khi lưu (mews/purifier). --}}
        <div class="page-content">{!! $page->content !!}</div>

        {{-- Thông tin công ty lấy từ một nơi (Thông tin công ty), luôn là bản mới nhất, giống nhau trên mọi site. --}}
        @include('theme::partials.company-info')
    </article>

    @if ($page->kind->hasQuoteForm())
        <section class="quote-request">
            <h2>Gửi yêu cầu báo giá</h2>
            @include('theme::partials.quote-form', ['product' => null])
        </section>
    @endif
@endsection
