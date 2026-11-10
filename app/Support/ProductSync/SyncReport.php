<?php

namespace App\Support\ProductSync;

/** Kết quả một lần đồng bộ, để báo lại cho người nhập. */
final class SyncReport
{
    public int $created = 0;

    public int $updated = 0;

    public int $hidden = 0;

    /** @var list<array{row: int, sku: string|null, reason: string}> */
    public array $errors = [];

    public function addError(int $row, ?string $sku, string $reason): void
    {
        $this->errors[] = ['row' => $row, 'sku' => $sku, 'reason' => $reason];
    }

    /** Tối đa $limit dòng lỗi để hiện cho người nhập. */
    public function errorLines(int $limit = 10): string
    {
        $lines = collect($this->errors)->take($limit)
            ->map(fn (array $error): string => "Dòng {$error['row']}".($error['sku'] ? " ({$error['sku']})" : '').": {$error['reason']}");

        if (count($this->errors) > $limit) {
            $lines->push('… và '.(count($this->errors) - $limit).' dòng lỗi khác');
        }

        return $lines->implode("\n");
    }

    public function summary(): string
    {
        return "{$this->created} tạo mới, {$this->updated} cập nhật, {$this->hidden} ẩn, ".count($this->errors).' dòng lỗi';
    }
}
