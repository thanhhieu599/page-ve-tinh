<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chứng từ có dòng hàng (báo giá, đơn hàng): đánh số theo năm (BG-2026-0001), tính tiền hàng, VAT, tổng.
 *
 * @property int $subtotal
 * @property int $vat_amount
 * @property int $total
 * @property string|float $vat_rate
 */
trait HasLineItems
{
    abstract public function items(): HasMany;

    abstract protected static function numberPrefix(): string;

    public static function nextNumber(?int $year = null): string
    {
        $prefix = static::numberPrefix().'-'.($year ?? (int) now()->timezone(config('app.display_timezone'))->format('Y')).'-';
        $last = static::query()->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $next = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** Tính lại thành tiền từng dòng, tiền hàng, VAT, tổng (đơn vị đồng, làm tròn). */
    public function recalculateTotals(): void
    {
        $subtotal = 0;
        foreach ($this->items()->get() as $item) {
            $line = (int) round((float) $item->quantity * (int) $item->unit_price);
            if ((int) $item->line_total !== $line) {
                $item->forceFill(['line_total' => $line])->saveQuietly();
            }
            $subtotal += $line;
        }

        $this->subtotal = $subtotal;
        $this->vat_amount = (int) round($subtotal * (float) $this->vat_rate / 100);
        $this->total = $this->subtotal + $this->vat_amount;
        $this->saveQuietly();
    }

    public static function money(int|float|null $amount): string
    {
        return number_format((int) $amount, 0, ',', '.').' đ';
    }
}
