<?php

namespace App\Support\Crm;

use App\Enums\Crm\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Xuất Excel để nhập vào MISA: chứng từ bán hàng (mỗi dòng hàng một dòng) và danh mục khách hàng.
 * Cột theo mẫu nhập khẩu "Chứng từ bán hàng" và "Khách hàng" của MISA (đối chiếu lại khi nhập lần đầu).
 */
final class MisaExport
{
    public const SALES_HEADER = ['Ngày chứng từ', 'Số chứng từ', 'Số hoá đơn', 'Mã khách hàng', 'Tên khách hàng', 'Mã số thuế', 'Địa chỉ',
        'Diễn giải', 'Mã hàng', 'Tên hàng', 'ĐVT', 'Số lượng', 'Đơn giá', 'Thành tiền', '% thuế GTGT', 'Tiền thuế GTGT', 'Hạn thanh toán'];

    public const CUSTOMER_HEADER = ['Mã khách hàng', 'Tên khách hàng', 'Mã số thuế', 'Địa chỉ', 'Tỉnh/Thành phố', 'Điện thoại', 'Email', 'Người liên hệ'];

    public static function customerCode(Customer $customer): string
    {
        return 'KH'.str_pad((string) $customer->id, 5, '0', STR_PAD_LEFT);
    }

    /** @return string đường dẫn file .xlsx tạm */
    public static function sales(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $orders = Order::query()->with(['customer', 'items'])->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('ordered_at', [$from->toDateString(), $to->toDateString()])->orderBy('ordered_at')->orderBy('number')->get();

        return self::write(self::SALES_HEADER, $orders->flatMap(function (Order $order): array {
            $rate = (float) $order->vat_rate;

            return $order->items->map(fn ($item): array => [
                $order->ordered_at->format('d/m/Y'), $order->number, (string) $order->invoice_number, self::customerCode($order->customer),
                $order->customer->name, (string) $order->customer->tax_code, (string) $order->customer->address, 'Bán hàng theo đơn '.$order->number,
                (string) $item->sku, $item->name, $item->unit, (float) $item->quantity, (int) $item->unit_price, (int) $item->line_total,
                $rate, (int) round($item->line_total * $rate / 100), $order->due_date?->format('d/m/Y') ?? '',
            ])->all();
        })->all());
    }

    public static function customers(): string
    {
        return self::write(self::CUSTOMER_HEADER, Customer::query()->with('primaryContact')->orderBy('id')->get()->map(fn (Customer $c): array => [
            self::customerCode($c), $c->name, (string) $c->tax_code, (string) $c->address, (string) $c->province,
            (string) $c->primaryContact?->phone, (string) $c->primaryContact?->email, (string) $c->primaryContact?->name,
        ])->all());
    }

    /** @param  list<string>  $header @param  list<list<mixed>>  $rows */
    private static function write(array $header, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'misa').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($header));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }
}
