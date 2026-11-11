{{--
    Header kiểu cửa hàng văn phòng phẩm: logo · ô tìm kiếm · hotline + nút báo giá,
    rồi thanh đỏ: "Danh mục sản phẩm" (xổ xuống bằng <details>, chạy cả khi tắt JavaScript) và menu trang.
--}}
@php($company = \App\Models\CompanyProfile::current())
@php([$phone, $tel] = [$company->value('phone'), $company->telHref()])
@php($categoryTree = \App\Support\SiteMenu::categoryTree())
@php($pages = \App\Support\SiteMenu::pages($site))
<div class="bg-accent text-xs text-white/90">
    <div class="container-site flex items-center justify-between gap-4 py-1.5">
        <p>Website chuyên đề thuộc {{ $company->value('name') }} · {{ $company->host() }}</p>
        @if ($phone)
            <p class="hidden sm:block">Hỗ trợ báo giá: <a class="font-semibold text-white no-underline hover:text-white" href="{{ $tel }}">{{ $phone }}</a></p>
        @endif
    </div>
</div>

<header class="bg-white">
    <div class="container-site flex flex-wrap items-center gap-x-6 gap-y-3 py-4">
        <a href="/" class="order-1 flex min-w-0 shrink items-center no-underline">
            @if ($logo = $site->logoUrl())
                <img src="{{ $logo }}" alt="{{ $site->name }}"@if ($logoWidth = $site->logoWidth()) width="{{ $logoWidth }}"@endif height="{{ \App\Models\Site::LOGO_HEIGHT }}">
            @else
                <span class="text-lg font-extrabold tracking-tight text-brand sm:text-2xl md:text-3xl">{{ $site->name }}</span>
            @endif
        </a>

        <form action="/tim-kiem" method="GET" role="search" class="search-box order-3 w-full md:order-2 md:w-auto md:flex-1">
            <label for="tim-kiem" class="sr-only">Tìm sản phẩm, mã hoặc bài viết</label>
            <input type="search" id="tim-kiem" name="q" value="{{ request()->routeIs('search') ? request()->query('q') : '' }}" placeholder="Tìm sản phẩm, mã sản phẩm…" maxlength="100">
            <button type="submit" aria-label="Tìm kiếm">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M10 2a8 8 0 0 1 6.32 12.9l5.39 5.4-1.42 1.4-5.39-5.38A8 8 0 1 1 10 2Zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12Z"/></svg>
            </button>
        </form>

        <div class="order-2 ml-auto flex items-center gap-4 md:order-3 md:ml-0">
            @if ($phone)
                <a href="{{ $tel }}" class="hidden text-right leading-tight no-underline lg:block">
                    <span class="block text-lg font-bold text-ink">{{ $phone }}</span>
                    <span class="block text-xs text-brand">Hỗ trợ báo giá theo số lượng</span>
                </a>
            @endif
            <a class="button button-primary whitespace-nowrap rounded-full px-4 text-sm" href="{{ \App\Http\Controllers\QuoteRequestController::PATH }}">Yêu cầu báo giá</a>
        </div>
    </div>
</header>

<div class="bg-brand text-white">
    <nav aria-label="Menu chính" class="container-site flex flex-wrap items-stretch md:flex-nowrap">
        <details class="category-menu group">
            <summary>
                <span aria-hidden="true">☰</span>
                <span>Danh mục sản phẩm</span>
                <span aria-hidden="true" class="ml-auto transition group-open:rotate-180">▾</span>
            </summary>
            <ul class="category-menu-list">
                @forelse ($categoryTree as $root)
                    <li class="category-menu-item">
                        <a href="{{ $root['href'] }}">{{ $root['label'] }}@if ($root['children'] !== [])<span aria-hidden="true">›</span>@endif</a>
                        @if ($root['children'] !== [])
                            <ul class="category-menu-sub">
                                @foreach ($root['children'] as $child)
                                    <li><a href="{{ $child['href'] }}">{{ $child['label'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @empty
                    <li class="category-menu-item"><a href="{{ \App\Http\Controllers\QuoteRequestController::PATH }}">Yêu cầu báo giá</a></li>
                @endforelse
            </ul>
        </details>

        <ul class="main-menu">
            @foreach ($pages as $item)
                {{-- Điện thoại: bỏ "Trang chủ" (bấm logo để về trang chủ) cho menu vừa một hàng. --}}
                <li @if ($item['href'] === '/') class="max-md:hidden" @endif><a href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
            @endforeach
        </ul>
    </nav>
</div>
