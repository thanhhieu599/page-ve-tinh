<?php

namespace App\Filament\Crm\Widgets;

use App\Filament\Crm\Support\CrmFields;
use App\Support\Crm\CrmReport;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Doanh số 12 tháng (đ)';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = CrmReport::monthlyRevenue(CrmFields::user());

        return [
            'datasets' => [['label' => 'Doanh số', 'data' => array_values($months), 'backgroundColor' => '#c1272d']],
            'labels' => array_keys($months),
        ];
    }
}
