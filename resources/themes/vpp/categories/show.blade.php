@extends('theme::layout')

@section('title', $category->seo_title ?: $category->name)

@section('canonical', \App\Support\CanonicalUrl::for($site, request(), withPage: true))

@section('meta_description', (string) $category->meta_description)

@section('content')
    @include('theme::partials.breadcrumb', ['trail' => collect($category->ancestry())
        ->map(fn ($item) => [$item->name, $item->is($category) ? null : $item->path()])->all()])

    <div class="category-layout">
    @if (($tree = \App\Support\SiteMenu::categoryTree()) !== [])
        <aside class="hidden md:block" aria-label="Danh mục sản phẩm">
            <h2 class="sidebar-title">Danh mục sản phẩm</h2>
            <ul class="category-sidebar">
                @foreach ($tree as $root)
                    <li class="category-menu-item">
                        <a href="{{ $root['href'] }}" @if ($root['href'] === $category->path()) aria-current="page" @endif>{{ $root['label'] }}</a>
                        @if ($root['children'] !== [])
                            <ul class="category-sidebar-sub">
                                @foreach ($root['children'] as $child)
                                    <li><a href="{{ $child['href'] }}" @if ($child['href'] === $category->path()) aria-current="page" @endif>{{ $child['label'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </aside>
    @endif
    <section>
        <h1>{{ $category->name }}</h1>

        @if ($category->description)
            <p>{{ $category->description }}</p>
        @endif

        @if ($products->isNotEmpty())
            <h2 class="section-label mt-6">Sản phẩm ({{ $products->count() }})</h2>
            <ul class="product-grid lg:grid-cols-4">
                @foreach ($products as $product)
                    @include('theme::partials.product-card', ['product' => $product])
                @endforeach
            </ul>
        @endif

        @if ($posts->isNotEmpty())
            <h2 class="section-label mt-10">Tin tức liên quan</h2>
            <ul class="news-grid md:grid-cols-3">
                @foreach ($posts as $post)
                    @include('theme::partials.news-card', ['post' => $post])
                @endforeach
            </ul>
            @if ($posts->hasPages())
                <nav class="pagination" aria-label="Phân trang">
                    @if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}" rel="prev">Trang trước</a>@endif
                    <span>Trang {{ $posts->currentPage() }} / {{ $posts->lastPage() }}</span>
                    @if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}" rel="next">Trang sau</a>@endif
                </nav>
            @endif
        @endif
    </section>
    </div>
@endsection
