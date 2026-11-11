{{-- Cột phải dùng chung (tin tức, bài viết): danh mục, sản phẩm nổi bật, hộp yêu cầu báo giá. --}}
@php($company = \App\Models\CompanyProfile::current())
@php($sidebarProducts = \App\Models\Product::query()->active()->where('is_featured', true)->with('image')->latest('id')->limit(5)->get())
<aside class="sidebar" aria-label="Thông tin thêm">
    <section class="sidebar-box sidebar-cta">
        <h2>Cần báo giá theo số lượng?</h2>
        <p>Gửi nhu cầu, bộ phận kinh doanh liên hệ trong giờ làm việc.</p>
        <a class="button rounded-full border-white bg-white text-brand hover:bg-white/90 hover:text-brand-dark" href="{{ \App\Http\Controllers\QuoteRequestController::PATH }}">Yêu cầu báo giá</a>
        @if ($company->value('phone'))
            <p class="mt-3">Hotline: <a class="font-bold text-white no-underline hover:text-white" href="{{ $company->telHref() }}">{{ $company->value('phone') }}</a></p>
        @endif
    </section>

    @if (($tree = \App\Support\SiteMenu::categoryTree()) !== [])
        <section class="sidebar-box">
            <h2>Danh mục sản phẩm</h2>
            <ul class="sidebar-links">
                @foreach ($tree as $root)
                    <li><a href="{{ $root['href'] }}">{{ $root['label'] }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($sidebarProducts->isNotEmpty())
        <section class="sidebar-box">
            <h2>Sản phẩm nổi bật</h2>
            <ul class="sidebar-products">
                @foreach ($sidebarProducts as $product)
                    <li>
                        <a href="{{ $product->visitUrl('cot-phai', request()->getPathInfo()) }}"@if ($product->hasBuyUrl()) target="_blank" rel="noopener" data-outbound="cot-phai"@endif>
                            @if ($product->image)
                                <img src="{{ $product->image->url(480) }}" alt="" width="56" height="56" loading="lazy" decoding="async">
                            @endif
                            <span>
                                <span class="sidebar-product-name">{{ $product->name }}</span>
                                <span class="sidebar-product-price">{{ $product->hasFreshPrice() ? $product->formattedPrice() : 'Liên hệ báo giá' }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</aside>
