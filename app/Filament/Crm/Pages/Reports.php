<?php

namespace App\Filament\Crm\Pages;

use App\Filament\Crm\Support\CrmFields;
use App\Support\Crm\CrmReport;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use Throwable;

/** Báo cáo bán hàng: theo site (ROI từng site vệ tinh), theo nhân viên, phễu báo giá, lý do thua. */
class Reports extends Page
{
    protected static ?string $slug = 'bao-cao';

    protected static ?string $title = 'Báo cáo bán hàng';

    protected static ?string $navigationLabel = 'Báo cáo';

    protected static string|\UnitEnum|null $navigationGroup = 'Phân tích';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.crm.reports';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public function report(): CrmReport
    {
        $default = CrmReport::thisMonth(CrmFields::user());
        $from = self::date($this->from) ?? $default->from;
        $to = self::date($this->to) ?? $default->to;

        return $from->lte($to) && $from->diffInDays($to) <= 366 ? new CrmReport($from, $to, CrmFields::user()) : $default;
    }

    private static function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, CrmReport::tz());
        } catch (Throwable) {
            return null;
        }

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
