@extends('theme::layout')

@section('title', $query === '' ? 'Tìm kiếm' : 'Tìm: '.$query)

@section('robots', 'noindex')

@section('content')
    @include('theme::partials.breadcrumb', ['trail' => [['Tìm kiếm', null]]])

    <h1>{{ $query === '' ? 'Tìm kiếm' : 'Kết quả cho “'.$query.'”' }}</h1>

    @if ($query === '')
        <p class="mt-3 text-muted">Nhập tên sản phẩm, mã hoặc chủ đề cần tìm vào ô tìm kiếm phía trên.</p>
    @elseif ($products->isEmpty() && $posts->isEmpty())
        <p class="mt-3 text-muted">Không tìm thấy kết quả. Thử từ khoá ngắn hơn, hoặc <a href="{{ \App\Http\Controllers\QuoteRequestController::PATH }}">gửi yêu cầu báo giá</a> để được tư vấn.</p>
    @endif

    @if ($products->isNotEmpty())
        <h2 class="section-label mt-6">Sản phẩm</h2>
        <ul class="product-grid">
            @foreach ($products as $product)
                @include('theme::partials.product-card', ['product' => $product])
            @endforeach
        </ul>
    @endif

    @if ($posts->isNotEmpty())
        <h2 class="section-label mt-10">Bài viết</h2>
        <ul class="post-list mt-4">
            @foreach ($posts as $post)
                <li>
                    <h2><a href="{{ $post->path() }}">{{ $post->title }}</a></h2>
                    @if ($post->excerpt)<p>{{ $post->excerpt }}</p>@endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
