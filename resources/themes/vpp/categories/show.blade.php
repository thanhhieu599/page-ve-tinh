@extends('theme::layout')

@section('title', $category->seo_title ?: $category->name)

@section('canonical', \App\Support\CanonicalUrl::for($site, request(), withPage: true))

@section('meta_description', (string) $category->meta_description)

@section('content')
    @include('theme::partials.breadcrumb', ['trail' => collect($category->ancestry())
        ->map(fn ($item) => [$item->name, $item->is($category) ? null : $item->path()])->all()])

    <section>
        <h1>{{ $category->name }}</h1>

        @if ($category->description)
            <p>{{ $category->description }}</p>
        @endif

        @if ($products->isNotEmpty())
            <h2 class="section-label mt-6">Sản phẩm</h2>
            <ul class="product-grid">
                @foreach ($products as $product)
                    @include('theme::partials.product-card', ['product' => $product])
                @endforeach
            </ul>
        @endif

        <h2 class="section-label mt-10">Kiến thức chọn mua</h2>
        @include('theme::partials.post-list', ['posts' => $posts])
    </section>
@endsection
