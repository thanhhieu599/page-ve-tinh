<?php

namespace App\Filament\Crm\Pages;

use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/** Tổng quan CRM: số liệu tháng này, doanh số 12 tháng, việc hôm nay, lead chưa giao. */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Tổng quan';

    protected static ?string $navigationLabel = 'Tổng quan';

    protected static string|\UnitEnum|null $navigationGroup = 'Tổng quan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    public function getColumns(): int|array
    {
        return 2;
    }
}
