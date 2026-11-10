<x-filament-panels::page>
    @php($report = $this->report())
    @php($sites = $this->sites())
    @php($number = fn (int|float $value, int $decimals = 0): string => number_format($value, $decimals, ',', '.'))

    <x-filament::section>
        <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
            <label>Từ ngày<br><input type="date" name="from" value="{{ $report->from->toDateString() }}"></label>
            <label>Đến ngày<br><input type="date" name="to" value="{{ $report->to->toDateString() }}"></label>
            <x-filament::button type="submit">Xem</x-filament::button>
        </form>
        <p style="margin-top: .75rem;">Từ {{ $report->from->format('d/m/Y') }} đến {{ $report->to->format('d/m/Y') }}. Search Console trễ khoảng 2 ngày và tính ngày theo giờ Thái Bình Dương; lead tính theo ngày gửi, doanh thu theo ngày chốt (giờ Việt Nam). Tổng lượt hiển thị/nhấp khớp màn hình Search Console; bảng top trang tính theo từng trang.</p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Tổng hợp theo site</x-slot>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: right;">
                        <th style="text-align: left;">Site</th>
                        <th>Lượt hiển thị</th>
                        <th>Lượt nhấp</th>
                        <th>CTR</th>
                        <th>Vị trí TB</th>
                        <th>Lead</th>
                        <th>Lead thắng</th>
                        <th>Doanh thu đã chốt</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report->rows($sites) as $row)
                        <tr style="text-align: right; border-top: 1px solid rgb(229 231 235);">
                            <td style="text-align: left;"><strong>{{ $row->site->name }}</strong><br><small>{{ $row->site->domain }}</small></td>
                            <td>{{ $number($row->impressions) }}</td>
                            <td>{{ $number($row->clicks) }}</td>
                            @php([$ctr, $position] = [$row->ctr(), $row->averagePosition()])
                            <td>{{ $ctr === null ? '–' : $number($ctr, 1).'%' }}</td>
                            <td>{{ $position === null ? '–' : $number($position, 1) }}</td>
                            <td>{{ $number($row->leads) }}</td>
                            <td>{{ $number($row->wonLeads) }}</td>
                            <td>{{ $number($row->revenue) }} đ</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">Bạn chưa được gán site nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    @foreach ($sites as $site)
        <x-filament::section>
            <x-slot name="heading">Top trang: {{ $site->name }}</x-slot>
            @php($pages = $report->topPages($site))
            @if ($pages->isEmpty())
                <p>Chưa có số liệu Search Console trong kỳ (kiểm tra cấu hình property của site).</p>
            @else
                <table style="width: 100%; border-collapse: collapse;">
                    <thead><tr><th style="text-align: left;">Trang</th><th style="text-align: right;">Lượt nhấp</th><th style="text-align: right;">Lượt hiển thị</th></tr></thead>
                    <tbody>
                        @foreach ($pages as $page)
                            <tr style="border-top: 1px solid rgb(229 231 235);">
                                <td style="word-break: break-all;"><a href="{{ $page->page_url }}" target="_blank" rel="noopener">{{ $page->page_url }}</a></td>
                                <td style="text-align: right;">{{ $number((int) $page->clicks) }}</td>
                                <td style="text-align: right;">{{ $number((int) $page->impressions) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
