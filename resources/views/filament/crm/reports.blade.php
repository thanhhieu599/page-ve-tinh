<x-filament-panels::page>
    @php($report = $this->report())
    @php($money = fn ($v) => number_format((int) $v, 0, ',', '.').' đ')
    @php($k = $report->kpis())
    <x-filament::section>
        <form method="GET" style="display:flex;gap:1rem;align-items:end;flex-wrap:wrap">
            <label>Từ ngày<br><input type="date" name="from" value="{{ $report->from->toDateString() }}"></label>
            <label>Đến ngày<br><input type="date" name="to" value="{{ $report->to->toDateString() }}"></label>
            <x-filament::button type="submit">Xem</x-filament::button>
        </form>
        <p style="margin-top:.75rem">Từ {{ $report->from->format('d/m/Y') }} đến {{ $report->to->format('d/m/Y') }}: <strong>{{ $money($k['revenue']) }}</strong> doanh số ({{ $k['orders'] }} đơn), {{ $k['leads'] }} lead mới. Công nợ còn phải thu {{ $money($k['outstanding']) }}, quá hạn {{ $money($k['overdue']) }}.</p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Theo site (nguồn khách)</x-slot>
        <table style="width:100%;border-collapse:collapse">
            <thead><tr style="text-align:right"><th style="text-align:left">Site</th><th>Lead</th><th>Lead thắng</th><th>Tỉ lệ chốt</th><th>Doanh số</th></tr></thead>
            <tbody>
                @forelse ($report->bySite() as $row)
                    <tr style="text-align:right;border-top:1px solid #e5e5e5"><td style="text-align:left">{{ $row['name'] }}</td><td>{{ $row['leads'] }}</td><td>{{ $row['won'] }}</td><td>{{ $row['conversion'] === null ? '–' : number_format($row['conversion'], 1, ',', '.').'%' }}</td><td>{{ $money($row['revenue']) }}</td></tr>
                @empty
                    <tr><td colspan="5">Chưa có số liệu trong kỳ.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Theo nhân viên</x-slot>
        <table style="width:100%;border-collapse:collapse">
            <thead><tr style="text-align:right"><th style="text-align:left">Nhân viên</th><th>Lead nhận</th><th>Báo giá</th><th>Báo giá thắng</th><th>Doanh số</th><th>Báo giá đang mở</th></tr></thead>
            <tbody>
                @foreach ($report->bySalesperson() as $row)
                    <tr style="text-align:right;border-top:1px solid #e5e5e5"><td style="text-align:left">{{ $row['name'] }}</td><td>{{ $row['leads'] }}</td><td>{{ $row['quotes'] }}</td><td>{{ $row['won'] }}</td><td>{{ $money($row['revenue']) }}</td><td>{{ $money($row['pipeline']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>

    <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(20rem,1fr))">
        <x-filament::section>
            <x-slot name="heading">Phễu báo giá (tạo trong kỳ)</x-slot>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr style="text-align:right"><th style="text-align:left">Bước</th><th>Số báo giá</th><th>Giá trị</th></tr></thead>
                <tbody>
                    @foreach ($report->pipeline() as $row)
                        <tr style="text-align:right;border-top:1px solid #e5e5e5"><td style="text-align:left">{{ $row['status']->getLabel() }}</td><td>{{ $row['count'] }}</td><td>{{ $money($row['value']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Lý do thua</x-slot>
            <table style="width:100%;border-collapse:collapse">
                <thead><tr style="text-align:right"><th style="text-align:left">Lý do</th><th>Số lần</th><th>Giá trị mất</th></tr></thead>
                <tbody>
                    @forelse ($report->lostReasons() as $row)
                        <tr style="text-align:right;border-top:1px solid #e5e5e5"><td style="text-align:left">{{ $row['reason'] }}</td><td>{{ $row['count'] }}</td><td>{{ $money($row['value']) }}</td></tr>
                    @empty
                        <tr><td colspan="3">Không có báo giá thua trong kỳ.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    </div>
</x-filament-panels::page>
