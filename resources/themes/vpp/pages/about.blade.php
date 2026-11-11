{{-- Trang giới thiệu: nội dung soạn trong trang quản trị + các khối có cấu trúc (bỏ trống thì không hiện). --}}
@php($hero = $page->extraImages('hero_image')[0] ?? null)
@php($stats = $page->extraList('stats', ['value', 'label']))
@php($milestones = $page->extraList('milestones', ['year', 'title']))
@php($values = $page->extraList('values', ['title']))
@php($gallery = $page->extraImages('gallery'))
@php($clients = $page->extraTags('clients'))

<article class="about-page">
    <header class="about-hero @if ($hero) md:grid-cols-2 @endif">
        <div>
            <p class="text-sm font-bold uppercase tracking-widest text-brand">Giới thiệu</p>
            <h1 class="mt-2 text-3xl md:text-4xl">{{ $page->title }}</h1>
            <div class="page-content">{!! $page->content !!}</div>
        </div>
        @if ($hero)
            <img src="{{ $hero['url'] }}" alt="{{ $page->title }}"@if ($hero['width']) width="{{ $hero['width'] }}" height="{{ $hero['height'] }}"@endif fetchpriority="high" decoding="async" class="about-hero-image">
        @endif
    </header>

    @if ($stats !== [])
        <ul class="about-stats">
            @foreach ($stats as $stat)
                <li><strong>{{ $stat['value'] }}</strong><span>{{ $stat['label'] }}</span></li>
            @endforeach
        </ul>
    @endif

    @if ($milestones !== [])
        <section class="mt-12" aria-labelledby="cot-moc">
            <h2 id="cot-moc" class="section-label">Chặng đường phát triển</h2>
            <ol class="about-timeline">
                @foreach ($milestones as $milestone)
                    <li>
                        <span class="about-timeline-year">{{ $milestone['year'] }}</span>
                        <div>
                            <h3>{{ $milestone['title'] }}</h3>
                            @if (($milestone['description'] ?? '') !== '')
                                <p>{{ $milestone['description'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @if ($values !== [])
        <section class="mt-12" aria-labelledby="gia-tri">
            <h2 id="gia-tri" class="section-label">Giá trị cốt lõi</h2>
            <ul class="about-values">
                @foreach ($values as $value)
                    <li>
                        <h3>{{ $value['title'] }}</h3>
                        @if (($value['description'] ?? '') !== '')
                            <p>{{ $value['description'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($gallery !== [])
        <section class="mt-12" aria-labelledby="hinh-anh">
            <h2 id="hinh-anh" class="section-label">Hình ảnh hoạt động</h2>
            <ul class="about-gallery">
                @foreach ($gallery as $image)
                    <li><img src="{{ $image['url'] }}" alt="Hình ảnh hoạt động – {{ $page->title }}"@if ($image['width']) width="{{ $image['width'] }}" height="{{ $image['height'] }}"@endif loading="lazy" decoding="async"></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($clients !== [])
        <section class="mt-12" aria-labelledby="khach-hang">
            <h2 id="khach-hang" class="section-label">Khách hàng tiêu biểu</h2>
            <ul class="about-clients">
                @foreach ($clients as $client)
                    <li>{{ $client }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @include('theme::partials.company-info')
</article>

@include('theme::partials.quote-cta', ['heading' => 'Hợp tác cùng chúng tôi'])
