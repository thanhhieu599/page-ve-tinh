<?php

namespace App\Support\ProductSync;

use Illuminate\Support\Str;

/**
 * Đọc file CSV xuất từ Sapo (hoặc file mẫu) thành ProductRecord.
 *
 * Nhận tên cột tiếng Việt có/không dấu, không phân biệt hoa thường; dấu phẩy hoặc
 * chấm phẩy; UTF-8 có hoặc không BOM. Cột "Thông số: <tên>" thành một dòng thông số.
 */
final class SapoCsvReader
{
    /** Tên cột chuẩn hoá (không dấu, thường) → trường. */
    private const COLUMNS = [
        'sku' => ['ma sku', 'sku', 'ma san pham', 'ma hang'],
        'name' => ['ten san pham', 'ten', 'ten hang', 'name'],
        'price' => ['gia ban le', 'gia ban', 'gia', 'price'],
        'buy_url' => ['link mua', 'url', 'duong dan', 'buy url', 'buy_url'],
        'status' => ['trang thai', 'status'],
    ];

    private const SPEC_PREFIX = 'thong so:';

    /** Giá trị cột Trạng thái nghĩa là ngừng bán. */
    private const DISCONTINUED = ['ngung ban', 'ngung kinh doanh', 'ngung', 'inactive', 'khong ban', '0'];

    public const TEMPLATE_HEADER = ['Tên sản phẩm', 'Mã SKU', 'Giá bán lẻ', 'Link mua', 'Thông số: Quy cách', 'Trạng thái'];

    /**
     * @return array<int, ProductRecord|string> khoá là số dòng trong file (dòng tiêu đề là 1)
     */
    public function read(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (! mb_check_encoding($content, 'UTF-8')) {
            return [1 => 'File không phải UTF-8. Trong Excel hãy chọn "Lưu dạng CSV UTF-8".'];
        }

        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $headerLine = array_shift($lines) ?? '';
        $delimiter = substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
        $rawHeader = array_map(fn (?string $cell) => trim((string) $cell), str_getcsv($headerLine, $delimiter, '"', ''));
        $header = array_map(self::normalize(...), $rawHeader);

        $map = $this->mapColumns($header);
        if (! isset($map['sku'])) {
            return [1 => 'Không tìm thấy cột "Mã SKU". Hãy dùng file mẫu hoặc đặt đúng tên cột.'];
        }

        $records = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line, $delimiter, '"', '');
            $records[$index + 2] = $this->toRecord($cells, $map, $header, $rawHeader);
        }

        return $records;
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int>
     */
    private function mapColumns(array $header): array
    {
        $map = [];
        foreach (self::COLUMNS as $field => $aliases) {
            foreach ($header as $position => $name) {
                if (in_array($name, $aliases, true)) {
                    $map[$field] = $position;

                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<string|null>  $cells
     * @param  array<string, int>  $map
     * @param  list<string>  $header  tên cột đã chuẩn hoá
     * @param  list<string>  $rawHeader  tên cột gốc (giữ dấu, dùng làm nhãn thông số)
     */
    private function toRecord(array $cells, array $map, array $header, array $rawHeader): ProductRecord|string
    {
        $cell = fn (string $field): ?string => isset($map[$field]) ? trim((string) ($cells[$map[$field]] ?? '')) : null;

        $price = $cell('price');
        if ($price !== null && $price !== '' && ! preg_match('/^[\d.,\s]+(đ|vnd|vnđ)?$/iu', $price)) {
            return "Giá \"{$price}\" không đọc được";
        }

        $specs = null;
        foreach ($header as $position => $name) {
            if (str_starts_with($name, self::SPEC_PREFIX)) {
                $value = trim((string) ($cells[$position] ?? ''));
                $specs ??= [];
                if ($value !== '') {
                    $label = trim((string) mb_substr($rawHeader[$position], (int) mb_strpos($rawHeader[$position], ':') + 1));
                    $specs[$label] = $value;
                }
            }
        }

        return new ProductRecord(
            sku: (string) $cell('sku'),
            name: $cell('name'),
            price: $price === null || $price === '' ? null : (int) preg_replace('/\D/', '', $price),
            buyUrl: $cell('buy_url'),
            specs: $specs,
            discontinued: in_array(self::normalize((string) $cell('status')), self::DISCONTINUED, true),
        );
    }

    private static function normalize(string $value): string
    {
        return trim(mb_strtolower(Str::ascii(rtrim(trim($value), '*'))));
    }
}
