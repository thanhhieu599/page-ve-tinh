<?php

namespace App\Filament\Crm\Widgets;

use App\Filament\Crm\Support\CrmFields;
use App\Models\Order;
use App\Support\Crm\CrmReport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CrmStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $k = CrmReport::thisMonth(CrmFields::user())->kpis();

        return [
            Stat::make('Doanh số tháng này', Order::money($k['revenue']))->description($k['orders'].' đơn hàng')->color('success'),
            Stat::make('Báo giá đang mở', Order::money($k['pipeline']))->description($k['open_quotes'].' báo giá chờ chốt')->color('warning'),
            Stat::make('Lead mới tháng này', (string) $k['leads'])->description('Từ mọi site và kênh nhập tay'),
            Stat::make('Công nợ quá hạn', Order::money($k['overdue']))->description('Tổng còn phải thu: '.Order::money($k['outstanding']))
                ->color($k['overdue'] > 0 ? 'danger' : 'gray'),
            Stat::make('Việc hôm nay', (string) $k['tasks_due'])->description('Đến hạn hoặc quá hạn')->color($k['tasks_due'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
