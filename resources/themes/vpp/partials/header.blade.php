{{--
    Header: dải xanh đậm (thuộc D&T, hotline) → logo + nút báo giá → thanh menu đỏ.
    Menu trên điện thoại dùng <details>: mở/đóng được khi tắt JavaScript.
--}}
@php($company = \App\Models\CompanyProfile::current())
@php([$phone, $tel] = [$company->value('phone'), $company->telHref()])
<div class="bg-navy text-xs text-white/90">
    <div class="container-site flex items-center justify-between gap-4 py-1.5">
        <p>Website chuyên đề thuộc {{ $company->value('name') }} · {{ $company->host() }}</p>
        @if ($phone)
            <p class="hidden sm:block">Hỗ trợ báo giá: <a class="font-semibold text-white no-underline hover:text-white" href="{{ $tel }}">{{ $phone }}</a></p>
        @endif
    </div>
</div>

<header class="border-b border-line bg-white">
    <div class="container-site flex items-center justify-between gap-4 py-3">
        <a href="/" class="flex items-center gap-2 no-underline">
            @if ($logo = $site->logoUrl())
                <img src="{{ $logo }}" alt="{{ $site->name }}"@if ($logoWidth = $site->logoWidth()) width="{{ $logoWidth }}"@endif height="{{ \App\Models\Site::LOGO_HEIGHT }}">
            @else
                <span class="text-xl font-extrabold tracking-tight text-brand md:text-2xl">{{ $site->name }}</span>
            @endif
        </a>

        <div class="flex items-center gap-4">
            @if ($phone)
                <a href="{{ $tel }}" class="hidden text-right leading-tight no-underline md:block">
                    <span class="block text-lg font-bold text-ink">{{ $phone }}</span>
                    <span class="block text-xs text-brand">Hỗ trợ báo giá theo số lượng</span>
                </a>
            @endif
            <a class="button button-primary whitespace-nowrap px-3 text-sm" href="{{ \App\Http\Controllers\QuoteRequestController::PATH }}">Yêu cầu báo giá</a>
        </div>
    </div>
</header>

@php($menu = \App\Support\SiteMenu::items($site))
<div class="bg-brand text-white">
    <div class="container-site">
        <details class="group md:hidden">
            <summary class="flex cursor-pointer list-none items-center gap-2 py-3 font-semibold">
                <span aria-hidden="true">☰</span> Danh mục và menu
            </summary>
            <ul class="border-t border-white/20 pb-2">
                @foreach ($menu as $item)
                    <li><a class="block py-2 text-white no-underline hover:text-white hover:underline" href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </details>
        <nav aria-label="Menu chính" class="hidden md:block">
            <ul class="flex flex-wrap items-center gap-x-7">
                @foreach ($menu as $item)
                    <li><a class="block py-3 font-semibold text-white no-underline hover:text-white hover:underline" href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    </div>
</div>
