<?php

namespace App\Filament\Crm\Resources\Catalog;

use App\Filament\Crm\Resources\Catalog\Pages\ListCatalog;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Site;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Danh mục sản phẩm của MỌI site (để báo giá, tra giá). Ở đây chỉ gán thương hiệu và đơn vị tính;
 * tên, giá, ảnh, link web chính sửa trong quản trị của từng site (hoặc nhập CSV Sapo).
 */
class CatalogResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = Product::class;

    protected static ?string $slug = 'san-pham';

    protected static string|\UnitEnum|null $navigationGroup = 'Sản phẩm';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $modelLabel = 'sản phẩm';

    protected static ?string $pluralModelLabel = 'Sản phẩm (mọi site)';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes()->with(['site', 'brand', 'category']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('brand_id')->label('Thương hiệu')->options(fn () => Brand::query()->orderBy('name')->pluck('name', 'id'))->searchable(),
            TextInput::make('unit')->label('Đơn vị tính (báo giá)')->placeholder('cái, đôi, hộp, ram, thùng…')->maxLength(30),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Sản phẩm')->searchable(['name', 'sku'])->wrap()->description(fn (Product $record): string => $record->sku),
                TextColumn::make('site.domain')->label('Site')->badge()->color('gray'),
                TextColumn::make('category.name')->label('Danh mục')->placeholder('—')->toggleable(),
                TextColumn::make('brand.name')->label('Thương hiệu')->placeholder('—'),
                TextColumn::make('unit')->label('ĐVT')->placeholder('—'),
                TextColumn::make('price')->label('Giá tham khảo')
                    ->state(fn (Product $record): string => $record->hasFreshPrice() ? $record->formattedPrice() : 'Hết hạn/chưa có'),
                TextColumn::make('is_active')->label('Đang bán')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Đang bán' : 'Ẩn')->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('site_id')->label('Site')->options(fn () => Site::query()->orderBy('name')->pluck('domain', 'id')),
                SelectFilter::make('brand_id')->label('Thương hiệu')->options(fn () => Brand::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([
                EditAction::make()->label('Thương hiệu, ĐVT'),
                Action::make('main')->label('Xem trên web chính')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->visible(fn (Product $record): bool => $record->hasBuyUrl())
                    ->url(fn (Product $record): string => (string) $record->buy_url, shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCatalog::route('/')];
    }
}
