{{--
    Trang giới thiệu kiểu web thương mại (cùng bố cục mọi site, nội dung nhập riêng trong quản trị):
    1 banner · 2 số liệu · 3 về chúng tôi · 4 chúng tôi cung cấp gì · 5 vì sao chọn · 6 khách hàng · 7 thông tin công ty + mời báo giá.
    Khối nào chưa có nội dung thì không hiện (trừ banner: chưa có ảnh thì nền đen).
--}}
@php($hero = $page->extraImages('hero_image')[0] ?? null)
@php($introImage = $page->extraImages('intro_image')[0] ?? null)
@php($stats = $page->extraList('stats', ['value', 'label']))
@php($intro = $page->extraText('intro'))
@php($tiles = \App\Support\CategoryTiles::for($page->aboutCategories(), skipEmpty: false))
@php($values = $page->extraList('values', ['title']))
@php($clients = $page->clients())

<article class="about-page">
    {{-- 1. Banner --}}
    <section class="{{ $hero ? 'about-banner' : 'about-banner about-banner-plain' }}" aria-label="Giới thiệu">
        @if ($hero)
            <img src="{{ $hero['url'] }}" alt=""@if ($hero['width']) width="{{ $hero['width'] }}" height="{{ $hero['height'] }}"@endif fetchpriority="high" decoding="async" class="about-banner-image">
        @endif
        <div class="about-banner-text">
            <p class="about-banner-kicker">{{ $page->title }}</p>
            <h1>{{ $page->extraText('headline') ?? $site->name }}</h1>
            @if ($summary = $page->extraText('summary'))
                <p class="about-banner-summary">{{ $summary }}</p>
            @endif
            <p class="about-banner-actions">
                <a class="button button-primary rounded-full px-6 py-3" href="{{ \App\Http\Controllers\QuoteRequestController::pathFrom($page->path()) }}">Yêu cầu báo giá</a>
                <a class="button rounded-full border-white px-6 py-3 text-white hover:bg-white hover:text-ink" href="{{ $tiles->isNotEmpty() ? '#cung-cap-noi-dung' : '/' }}">Xem sản phẩm</a>
            </p>
        </div>
    </section>

    {{-- 2. Số liệu --}}
    @if ($stats !== [])
        <ul class="about-stats">
            @foreach ($stats as $stat)
                <li><strong>{{ $stat['value'] }}</strong><span>{{ $stat['label'] }}</span></li>
            @endforeach
        </ul>
    @endif

    {{-- 3. Về chúng tôi --}}
    @if ($intro)
        <section class="{{ $introImage ? 'about-intro about-intro-with-image' : 'about-intro' }}" aria-labelledby="ve-chung-toi">
            <div>
                <h2 id="ve-chung-toi" class="section-label">Về chúng tôi</h2>
                <div class="about-intro-text">{!! nl2br(e($intro)) !!}</div>
            </div>
            @if ($introImage)
                <img src="{{ $introImage['url'] }}" alt="Về {{ $site->name }}"@if ($introImage['width']) width="{{ $introImage['width'] }}" height="{{ $introImage['height'] }}"@endif loading="lazy" decoding="async">
            @endif
        </section>
    @endif

    {{-- 4. Chúng tôi cung cấp gì --}}
    @if ($tiles->isNotEmpty())
        <section class="mt-12" aria-labelledby="cung-cap" id="cung-cap-noi-dung">
            <h2 id="cung-cap" class="section-label">Chúng tôi cung cấp gì</h2>
            <ul class="about-supply">
                @foreach ($tiles as $tile)
                    <li>
                        <a href="{{ $tile['category']->path() }}">
                            <span class="about-supply-image">
                                @if ($tile['image']?->image)
                                    <img src="{{ $tile['image']->image->url(480) }}" alt="" width="480" height="{{ $tile['image']->image->heightAt(480) }}" loading="lazy" decoding="async">
                                @else
                                    <span aria-hidden="true">{{ mb_substr($tile['category']->name, 0, 1) }}</span>
                                @endif
                            </span>
                            <span class="about-supply-name">{{ $tile['category']->name }}</span>
                            @if ($tile['count'] > 0)
                                <span class="about-supply-count">{{ $tile['count'] }} sản phẩm</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- 5. Vì sao chọn --}}
    @if ($values !== [])
        <section class="mt-12" aria-labelledby="vi-sao-chon">
            <h2 id="vi-sao-chon" class="section-label">Vì sao chọn {{ $site->name }}</h2>
            <ul class="about-values">
                @foreach ($values as $value)
                    <li>
                        <span class="about-value-icon">{!! \App\Support\AboutIcons::svg($value['icon'] ?? null) !!}</span>
                        <h3>{{ $value['title'] }}</h3>
                        @if (($value['description'] ?? '') !== '')
                            <p>{{ $value['description'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- 6. Khách hàng tiêu biểu --}}
    @if ($clients !== [])
        <section class="mt-12" aria-labelledby="khach-hang">
            <h2 id="khach-hang" class="section-label">Khách hàng tiêu biểu</h2>
            <ul class="about-clients">
                @foreach ($clients as $client)
                    <li>
                        @if ($client['logo'])
                            <img src="{{ $client['logo']['url'] }}" alt="{{ $client['name'] }}"@if ($client['logo']['width']) width="{{ $client['logo']['width'] }}" height="{{ $client['logo']['height'] }}"@endif loading="lazy" decoding="async">
                        @else
                            <span class="about-client-name">{{ $client['name'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- 7. Thông tin công ty --}}
    @include('theme::partials.company-info')
</article>

@include('theme::partials.quote-cta', ['heading' => 'Hợp tác cùng '.$site->name])
