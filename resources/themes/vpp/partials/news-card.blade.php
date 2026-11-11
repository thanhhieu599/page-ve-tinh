{{-- Thẻ tin: ảnh đại diện, danh mục, ngày đăng (giờ Việt Nam), tiêu đề, tóm tắt. --}}
@php($cover = $post->cover())
@php($category = $post->primaryCategory())
<li class="news-card">
    <a href="{{ $post->path() }}" class="news-card-image" tabindex="-1" aria-hidden="true">
        @if ($cover)
            <img src="{{ $cover['url'] }}" alt=""@if ($cover['width']) width="{{ $cover['width'] }}" height="{{ $cover['height'] }}"@endif loading="lazy" decoding="async">
        @else
            <span class="news-card-noimage">{{ $site->name }}</span>
        @endif
    </a>
    <div class="news-card-body">
        <p class="news-card-meta">
            @if ($category)<span class="text-brand">{{ $category->name }}</span> · @endif
            <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->timezone(config('app.display_timezone'))->format('d/m/Y') }}</time>
        </p>
        <h3><a href="{{ $post->path() }}">{{ $post->title }}</a></h3>
        @if ($post->excerpt)
            <p class="news-card-excerpt">{{ \Illuminate\Support\Str::limit($post->excerpt, 150) }}</p>
        @endif
    </div>
</li>
