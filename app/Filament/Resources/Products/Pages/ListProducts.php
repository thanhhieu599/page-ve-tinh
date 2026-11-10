<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\ProductSource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\TenantSite;
use App\Models\Product;
use App\Support\ProductSync\ProductSync;
use App\Support\ProductSync\SapoCsvReader;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importSapo')
                ->label('Nhập CSV từ Sapo')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalHeading('Nhập sản phẩm từ file CSV Sapo')
                ->modalDescription('Khớp theo mã SKU. Chỉ cập nhật tên, thông số, link mua, giá; mô tả, ảnh, bài viết liên quan của site không bị đổi.')
                ->schema([
                    FileUpload::make('file')
                        ->label('File CSV (UTF-8)')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->maxSize(5120)
                        ->storeFiles(false)
                        ->required(),
                    Toggle::make('full_file')
                        ->label('Đây là danh sách đầy đủ')
                        ->helperText('Bật khi file chứa toàn bộ sản phẩm đang bán: SKU không có trong file sẽ bị ẩn (sản phẩm tạo tay không bị ảnh hưởng).'),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'];
                    assert($file instanceof TemporaryUploadedFile);

                    $report = app(ProductSync::class)->sync(
                        (int) TenantSite::id(),
                        app(SapoCsvReader::class)->read($file->getRealPath()),
                        ProductSource::Csv,
                        (bool) ($data['full_file'] ?? false),
                    );

                    $errors = collect($report->errors)->take(10)
                        ->map(fn (array $error): string => "Dòng {$error['row']}".($error['sku'] ? " ({$error['sku']})" : '').": {$error['reason']}")
                        ->implode("\n");

                    Notification::make()
                        ->title('Nhập CSV xong: '.$report->summary())
                        ->body($errors !== '' ? $errors.(count($report->errors) > 10 ? "\n…" : '') : null)
                        ->{$report->errors === [] ? 'success' : 'warning'}()
                        ->persistent()
                        ->send();
                }),
            Action::make('downloadTemplate')
                ->label('Tải file mẫu')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => response()->streamDownload(function (): void {
                    echo "\xEF\xBB\xBF".implode(',', SapoCsvReader::TEMPLATE_HEADER)."\r\n";
                    echo 'Giấy Double A A4 80gsm,DA-A4-80,89000,https://trangthietbi.vn/giay-double-a-a4-80gsm,500 tờ/ram,Đang bán'."\r\n";
                }, 'mau-nhap-san-pham.csv', ['Content-Type' => 'text/csv; charset=UTF-8'])),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tất cả'),
            'expiring' => Tab::make('Sắp hết hạn giá')
                ->modifyQueryUsing(fn (Builder $query) => $query->priceExpiringSoon())
                ->badge(Product::query()->priceExpiringSoon()->count())
                ->badgeColor('warning'),
            'expired' => Tab::make('Hết hạn giá')
                ->modifyQueryUsing(fn (Builder $query) => $query->priceExpired())
                ->badge(Product::query()->priceExpired()->count())
                ->badgeColor('danger'),
        ];
    }
}
