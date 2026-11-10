{{-- Danh sách bài dạng thẻ, dùng chung cho trang danh mục và trang tác giả. --}}
<ul class="post-list">
    @forelse ($posts as $item)
        <li>
            <h2><a href="{{ $item->path() }}">{{ $item->title }}</a></h2>
            @if ($item->excerpt)
                <p>{{ $item->excerpt }}</p>
            @endif
        </li>
    @empty
        <li>Chưa có bài viết.</li>
    @endforelse
</ul>

@if ($posts->hasPages())
    <nav class="pagination" aria-label="Phân trang">
        @if ($posts->previousPageUrl())
            <a href="{{ $posts->previousPageUrl() }}" rel="prev">Trang trước</a>
        @endif
        <span>Trang {{ $posts->currentPage() }} / {{ $posts->lastPage() }}</span>
        @if ($posts->nextPageUrl())
            <a href="{{ $posts->nextPageUrl() }}" rel="next">Trang sau</a>
        @endif
    </nav>
@endif
