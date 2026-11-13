@php($money = fn ($v) => number_format((int) $v, 0, ',', '.'))
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title>Báo giá {{ $quote->number }} – {{ $quote->customer->name }}</title>
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, 'Helvetica Neue', sans-serif; font-size: 12.5px; color: #1a1a1a; margin: 0; }
        .sheet { max-width: 186mm; margin: 0 auto; padding: 8mm 0; }
        .head { display: flex; justify-content: space-between; gap: 16px; border-bottom: 3px solid #c1272d; padding-bottom: 10px; }
        .head h1 { color: #c1272d; font-size: 18px; margin: 0 0 4px; }
        .head p { margin: 2px 0; }
        .title { text-align: center; margin: 18px 0 4px; font-size: 22px; letter-spacing: 1px; }
        .meta { text-align: center; margin: 0 0 14px; color: #555; }
        .to { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 6px; vertical-align: top; }
        th { background: #f3f3f3; }
        td.n { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; }
        .words { margin: 8px 0 14px; font-style: italic; }
        .terms h3 { font-size: 13px; margin: 10px 0 4px; }
        .sign { display: flex; justify-content: flex-end; margin-top: 26px; text-align: center; }
        .sign div { width: 60mm; }
        .print { position: fixed; top: 12px; right: 12px; background: #c1272d; color: #fff; border: 0; padding: 10px 16px; border-radius: 6px; font-size: 14px; cursor: pointer; }
        @media print { .print { display: none; } .sheet { padding: 0; } }
    </style>
</head>
<body>
<button class="print" onclick="window.print()">In / Lưu PDF</button>
<div class="sheet">
    <div class="head">
        <div>
            <h1>{{ $company->value('legal_name') }}</h1>
            @if ($company->address())<p>Địa chỉ: {{ $company->address() }}</p>@endif
            @if ($company->value('tax_id'))<p>Mã số thuế: {{ $company->value('tax_id') }}</p>@endif
            <p>@if ($company->value('phone'))Điện thoại: {{ $company->value('phone') }}@endif @if ($company->value('email')) · Email: {{ $company->value('email') }}@endif</p>
            @if ($company->bank_account)<p>Tài khoản: {{ $company->bank_account }}</p>@endif
        </div>
        <div style="text-align:right">
            <p><strong>Số: {{ $quote->number }}</strong></p>
            <p>Ngày: {{ $quote->created_at->timezone(config('app.display_timezone'))->format('d/m/Y') }}</p>
            @if ($quote->valid_until)<p>Hiệu lực đến: {{ $quote->valid_until->format('d/m/Y') }}</p>@endif
        </div>
    </div>

    <h2 class="title">BẢNG BÁO GIÁ</h2>
    <p class="meta">Kính gửi Quý khách hàng, {{ $company->value('name') }} trân trọng gửi báo giá như sau:</p>

    <div class="to">
        <div><strong>Khách hàng:</strong> {{ $quote->customer->name }}</div>
        <div><strong>Mã số thuế:</strong> {{ $quote->customer->tax_code ?? '—' }}</div>
        <div><strong>Địa chỉ:</strong> {{ $quote->customer->address ?? '—' }}</div>
        <div><strong>Người nhận:</strong> {{ $quote->contact?->name ?? '—' }}@if ($quote->contact?->phone) · {{ $quote->contact->phone }}@endif</div>
    </div>

    <table>
        <thead>
            <tr><th>STT</th><th>Tên hàng, quy cách</th><th>Mã</th><th>ĐVT</th><th>Số lượng</th><th>Đơn giá (đ)</th><th>Thành tiền (đ)</th></tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $i => $item)
                <tr>
                    <td class="n">{{ $i + 1 }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->unit }}</td>
                    <td class="n">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="n">{{ $money($item->unit_price) }}</td>
                    <td class="n">{{ $money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="6" class="n">Cộng tiền hàng</td><td class="n">{{ $money($quote->subtotal) }}</td></tr>
            <tr><td colspan="6" class="n">Thuế GTGT {{ rtrim(rtrim((string) $quote->vat_rate, '0'), '.') }}%</td><td class="n">{{ $money($quote->vat_amount) }}</td></tr>
            <tr><td colspan="6" class="n">Tổng thanh toán</td><td class="n">{{ $money($quote->total) }}</td></tr>
        </tfoot>
    </table>
    <p class="words">Bằng chữ: {{ \App\Support\Crm\VietnameseNumber::words((int) $quote->total) }}.</p>

    <div class="terms">
        @if ($quote->delivery_terms)<h3>Điều kiện giao hàng</h3><p>{!! nl2br(e($quote->delivery_terms)) !!}</p>@endif
        @if ($quote->payment_terms)<h3>Điều kiện thanh toán</h3><p>{!! nl2br(e($quote->payment_terms)) !!}</p>@endif
        @if ($quote->notes)<h3>Ghi chú</h3><p>{!! nl2br(e($quote->notes)) !!}</p>@endif
    </div>

    <div class="sign">
        <div>
            <p><strong>{{ $company->value('legal_name') }}</strong></p>
            <p style="margin-top:60px">{{ $quote->owner?->name }}</p>
        </div>
    </div>
</div>
</body>
</html>
