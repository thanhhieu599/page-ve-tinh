<?php

namespace App\Filament\Crm\Support;

use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\Select;

/** Ô chọn dùng lại trong CRM: khách hàng, nhân viên phụ trách, sản phẩm (mọi site). */
final class CrmFields
{
    public static function user(): User
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user;
    }

    public static function customer(string $name = 'customer_id'): Select
    {
        return Select::make($name)->label('Khách hàng')
            ->relationship('customer', 'name', modifyQueryUsing: fn ($query) => $query->visibleTo(self::user()))
            ->searchable(['name', 'tax_code'])->preload();
    }

    public static function owner(string $name = 'owner_id', string $label = 'Nhân viên phụ trách'): Select
    {
        return Select::make($name)->label($label)
            ->options(fn (): array => User::salesPeople()->pluck('name', 'id')->all())
            ->searchable()
            ->default(fn (): int => self::user()->id)
            ->disabled(fn (): bool => ! self::user()->isSalesManager())
            ->dehydrated();
    }

    /** @return array<int, string> sản phẩm đang bán của mọi site: "Tên (SKU) – site" */
    public static function productOptions(?string $search = null): array
    {
        return Product::withoutGlobalScopes()->with('site')->where('is_active', true)
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->orderBy('name')->limit(50)->get()
            ->mapWithKeys(fn (Product $p) => [$p->id => "{$p->name} ({$p->sku}) – {$p->site?->domain}"])->all();
    }
}
