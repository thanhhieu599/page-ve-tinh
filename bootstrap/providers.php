<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\CrmPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    CrmPanelProvider::class,
];
