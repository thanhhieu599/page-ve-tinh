<?php

namespace App\Filament\Crm\Pages;

use App\Filament\Crm\Support\CrmFields;
use App\Support\Crm\CrmReport;
use App\Support\Crm\MisaExport;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Xuất Excel sang MISA (chứng từ bán hàng, danh mục khách hàng). Trưởng phòng KD/Admin. */
class MisaExportPage extends Page
{
    protected static ?string $slug = 'xuat-misa';

    protected static ?string $title = 'Xuất Excel MISA';

    protected static string|\UnitEnum|null $navigationGroup = 'Phân tích';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected string $view = 'filament.crm.misa-export';

    public static function canAccess(): bool
    {
        return auth()->check() && CrmFields::user()->isSalesManager();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sales')->label('Tải chứng từ bán hàng')->icon(Heroicon::OutlinedArrowDownTray)
                ->schema([
                    DatePicker::make('from')->label('Từ ngày')->default(now()->startOfMonth())->required()->native(false)->displayFormat('d/m/Y'),
                    DatePicker::make('to')->label('Đến ngày')->default(now())->required()->native(false)->displayFormat('d/m/Y'),
                ])
                ->action(function (array $data): BinaryFileResponse {
                    abort_unless(self::canAccess(), 403);
                    [$from, $to] = [CarbonImmutable::parse($data['from'], CrmReport::tz()), CarbonImmutable::parse($data['to'], CrmReport::tz())];

                    return response()->download(MisaExport::sales($from, $to), 'misa-ban-hang-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xlsx')->deleteFileAfterSend();
                }),
            Action::make('customers')->label('Tải danh mục khách hàng')->icon(Heroicon::OutlinedUsers)->color('gray')
                ->action(function (): BinaryFileResponse {
                    abort_unless(self::canAccess(), 403);

                    return response()->download(MisaExport::customers(), 'misa-khach-hang.xlsx')->deleteFileAfterSend();
                }),
        ];
    }
}
