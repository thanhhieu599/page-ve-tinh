<?php

namespace App\Filament\Crm\Support;

use App\Models\Order;
use App\Models\Product;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

/** Dòng hàng của báo giá/đơn hàng: chọn sản phẩm (mọi site) tự điền tên, mã, đơn vị, giá tham khảo. */
final class LineItems
{
    public static function repeater(): Repeater
    {
        return Repeater::make('items')->label('Hàng hoá')
            ->relationship()
            ->orderColumn('sort')
            ->reorderable()
            ->defaultItems(1)
            ->minItems(1)
            ->addActionLabel('Thêm dòng hàng')
            ->columns(12)
            ->schema([
                Select::make('product_id')->label('Sản phẩm (tuỳ chọn)')
                    ->options(fn (): array => CrmFields::productOptions())
                    ->getSearchResultsUsing(fn (string $search): array => CrmFields::productOptions($search))
                    ->getOptionLabelUsing(fn ($value): ?string => Product::withoutGlobalScopes()->find($value)?->name)
                    ->searchable()->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                        $product = $state ? Product::withoutGlobalScopes()->find($state) : null;
                        if ($product) {
                            $set('name', $product->name);
                            $set('sku', $product->sku);
                            $set('unit', $product->unit ?: 'cái');
                            if ($product->price) {
                                $set('unit_price', $product->price);
                            }
                        }
                    })
                    ->columnSpan(4),
                TextInput::make('name')->label('Tên hàng, quy cách')->required()->maxLength(255)->columnSpan(4),
                TextInput::make('sku')->label('Mã')->maxLength(100)->columnSpan(2),
                TextInput::make('unit')->label('ĐVT')->default('cái')->required()->maxLength(30)->columnSpan(2),
                TextInput::make('quantity')->label('Số lượng')->numeric()->minValue(0.01)->default(1)->required()->columnSpan(3),
                TextInput::make('unit_price')->label('Đơn giá (đ, chưa VAT)')->integer()->minValue(0)->required()->columnSpan(4),
                TextEntry::make('line')->label('Thành tiền')->columnSpan(5)
                    ->state(fn (Get $get): string => Order::money(round((float) $get('quantity') * (int) $get('unit_price')))),
            ]);
    }

    /** Tổng tiền đã tính khi lưu (tiền hàng, VAT, tổng). */
    public static function totals(): Section
    {
        return Section::make('Tổng cộng')->columns(3)->visibleOn('edit')->schema([
            TextEntry::make('subtotal')->label('Tiền hàng')->state(fn (?Model $record): string => Order::money($record?->subtotal)),
            TextEntry::make('vat_amount')->label('Thuế GTGT')->state(fn (?Model $record): string => Order::money($record?->vat_amount).' ('.rtrim(rtrim((string) $record?->vat_rate, '0'), '.').'%)'),
            TextEntry::make('total')->label('Tổng thanh toán')->weight('bold')->state(fn (?Model $record): string => Order::money($record?->total)),
        ]);
    }
}
