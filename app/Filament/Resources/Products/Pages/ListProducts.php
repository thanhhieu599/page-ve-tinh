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

    /** Giá còn hạn từ PRICE_WARNING_DAYS ngày trở xuống: cần cập nhật hoặc xác nhận lại. */
    public static function expiringSoon(Builder $query): Builder
    {
        return $query->whereNotNull('price')
            ->where('price_updated_at', '>=', now()->subDays(Product::PRICE_VALID_DAYS))
            ->where('price_updated_at', '<=', now()->subDays(Product::PRICE_VALID_DAYS - Product::PRICE_WARNING_DAYS));
    }

    public static function expired(Builder $query): Builder
    {
        return $query->whereNotNull('price')->where('price_updated_at', '<', now()->subDays(Product::PRICE_VALID_DAYS));
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tất cả'),
            'expiring' => Tab::make('Sắp hết hạn giá')
                ->modifyQueryUsing(fn (Builder $query) => self::expiringSoon($query))
                ->badge(self::expiringSoon(Product::query())->count())
                ->badgeColor('warning'),
            'expired' => Tab::make('Hết hạn giá')
                ->modifyQueryUsing(fn (Builder $query) => self::expired($query))
                ->badge(self::expired(Product::query())->count())
                ->badgeColor('danger'),
        ];
    }
}
