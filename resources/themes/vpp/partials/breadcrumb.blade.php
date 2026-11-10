{{-- $trail: danh sách [nhãn, đường dẫn|null]. Mục cuối là trang hiện tại, không có link. --}}
<nav aria-label="Breadcrumb" class="breadcrumb">
    <ol>
        <li><a href="/">Trang chủ</a></li>
        @foreach ($trail as [$label, $url])
            <li>
                @if ($url)
                    <a href="{{ $url }}">{{ $label }}</a>
                @else
                    <span aria-current="page">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
