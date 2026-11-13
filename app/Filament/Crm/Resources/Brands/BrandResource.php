<?php

namespace App\Filament\Crm\Resources\Brands;

use App\Filament\Crm\Resources\Brands\Pages\ManageBrands;
use App\Models\Brand;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Thương hiệu (TOWA, 3M, Deli, Thiên Long, Double A…), dùng chung mọi site. */
class BrandResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = Brand::class;

    protected static ?string $slug = 'thuong-hieu';

    protected static string|\UnitEnum|null $navigationGroup = 'Sản phẩm';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'thương hiệu';

    protected static ?string $pluralModelLabel = 'Thương hiệu';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Tên')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('website')->label('Website')->url()->maxLength(255),
            Textarea::make('description')->label('Ghi chú (đại lý uỷ quyền, chứng nhận…)')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('name')->columns([
            TextColumn::make('name')->label('Thương hiệu')->searchable(),
            TextColumn::make('products_count')->label('Số sản phẩm')->counts('products'),
            TextColumn::make('website')->label('Website')->placeholder('—'),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBrands::route('/')];
    }
}
