{{-- $trail: danh sách [nhãn, đường dẫn|null]. Mục cuối là trang hiện tại, không có link. --}}
{{-- Schema BreadcrumbList sinh từ cùng danh sách này, nên luôn khớp breadcrumb hiển thị. --}}
@php($fullTrail = [['Trang chủ', '/'], ...$trail])
@push('structured-data')
    {!! \App\Support\StructuredData::breadcrumbs($site, $fullTrail, \App\Support\CanonicalUrl::for($site, request()))->toScript() !!}
@endpush
<nav aria-label="Breadcrumb" class="breadcrumb">
    <ol>
        @foreach ($fullTrail as [$label, $url])
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
