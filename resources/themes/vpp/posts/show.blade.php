@extends('theme::layout')

@section('title', $post->seo_title ?: $post->title)

@section('meta_description', (string) ($post->meta_description ?: $post->excerpt))

@section('content')
    @include('theme::partials.breadcrumb', ['trail' => [
        ...collect($post->primaryCategory()?->ancestry() ?? [])->map(fn ($category) => [$category->name, $category->path()])->all(),
        [$post->title, null],
    ]])

    <article>
        <h1>{{ $post->title }}</h1>

        @if ($post->author)
            <p class="byline">Tác giả: <a href="{{ $post->author->path() }}" rel="author">{{ $post->author->name }}</a></p>
        @endif

        {{-- Nội dung đã được lọc HTML khi lưu (mews/purifier). --}}
        {{-- Ảnh trong bài được gắn alt/kích thước/srcset/tải lười khi hiển thị (ContentImages). --}}
        <div class="post-content">{!! $content !!}</div>

        @if ($post->faqs->isNotEmpty())
            <section class="faq">
                <h2>Câu hỏi thường gặp</h2>
                @foreach ($post->faqs as $faq)
                    <h3>{{ $faq->question }}</h3>
                    <p>{!! nl2br(e($faq->answer)) !!}</p>
                @endforeach
            </section>
        @endif
    </article>
@endsection
