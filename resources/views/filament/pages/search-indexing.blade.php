<x-filament-panels::page>
    @php($site = $this->site())
    @php($readiness = $this->readiness())

    <x-filament::section>
        <x-slot name="heading">Trạng thái</x-slot>
        @if ($site->is_indexable)
            <p>Đang cho Google index. Sitemap: {{ $site->domain }}/sitemap.xml</p>
        @else
            <p>Đang chặn Google (noindex). Mọi trang gắn noindex, robots.txt chặn thu thập.</p>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Điều kiện mở index</x-slot>
        <ul style="display: grid; gap: .75rem;">
            @foreach ($readiness->items as $item)
                <li style="display: flex; gap: .5rem; align-items: baseline;">
                    @if ($item->passed)
                        <span style="color: rgb(22 163 74);" aria-label="Đạt">✔</span>
                    @else
                        <span style="color: rgb(220 38 38);" aria-label="Còn thiếu">✘</span>
                    @endif
                    <span>
                        <strong>{{ $item->label }}</strong>
                        <span> – {{ $item->detail }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
        @unless ($readiness->isReady())
            <p style="margin-top: 1rem;">Chỉ Admin bật được index, và chỉ khi mọi mục ở trên đã đạt.</p>
        @endunless
    </x-filament::section>
</x-filament-panels::page>
