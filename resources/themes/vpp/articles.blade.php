@extends('theme::layout')

@section('title', 'Tin tức')

@section('canonical', \App\Support\CanonicalUrl::for($site, request(), withPage: true))

@section('meta_description', 'Tin tức văn phòng phẩm, vật tư cho nhà máy và doanh nghiệp: sản phẩm mới, giá cả, kinh nghiệm chọn mua.')

@section('content')
    @include('theme::partials.breadcrumb', ['trail' => [['Tin tức', null]]])

    <div class="content-with-sidebar">
        <section>
            <h1>Tin tức</h1>
            <p class="mt-2 text-muted">Tin văn phòng phẩm, sản phẩm mới và kinh nghiệm chọn mua cho doanh nghiệp.</p>

            @if ($posts->isEmpty())
                <p class="mt-6">Chưa có tin nào.</p>
            @else
                <ul class="news-grid">
                    @foreach ($posts as $post)
                        @include('theme::partials.news-card', ['post' => $post])
                    @endforeach
                </ul>
            @endif

            @if ($posts->hasPages())
                <nav class="pagination" aria-label="Phân trang">
                    @if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}" rel="prev">Trang trước</a>@endif
                    <span>Trang {{ $posts->currentPage() }} / {{ $posts->lastPage() }}</span>
                    @if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}" rel="next">Trang sau</a>@endif
                </nav>
            @endif
        </section>

        @include('theme::partials.sidebar')
    </div>
@endsection
