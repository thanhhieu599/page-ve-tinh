<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
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
