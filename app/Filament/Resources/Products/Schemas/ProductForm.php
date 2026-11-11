<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Resources\Posts\Actions\InsertMediaAction;
use App\Filament\Support\TenantSite;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Thông tin từ Sapo')
                    ->description('Khi nhập CSV/Sapo, các trường này sẽ được cập nhật theo Sapo.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Tên sản phẩm')->required()->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                                if (blank($get('slug')) || $get('slug') === Str::slug((string) $old)) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('sku')->label('Mã SKU')->required()->maxLength(100)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => TenantSite::unique($rule)),
                        TextInput::make('price')->label('Giá tham khảo (VNĐ, chưa VAT)')
                            ->integer()->minValue(0)->maxValue(Product::MAX_PRICE)->suffix('đ')
                            ->helperText('Đổi giá sẽ tự cập nhật ngày giá. Giá hiện trên site đến hết ngày thứ '.Product::PRICE_VALID_DAYS.' kể từ ngày cập nhật. Để trống hoặc 0 = chưa có giá.'),
                        TextInput::make('buy_url')->label('Link mua trên trangthietbi.vn')->maxLength(255)
                            ->placeholder('https://trangthietbi.vn/...')
                            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                if (! Product::isAllowedBuyUrl(is_string($value) ? $value : null)) {
                                    $fail('Link mua phải là trang trên https://trangthietbi.vn.');
                                }
                            }),
                        KeyValue::make('specs')->label('Thông số')->keyLabel('Thông số')->valueLabel('Giá trị')
                            ->columnSpanFull(),
                    ]),

                Section::make('Nội dung trên site')
                    ->description('Do người nhập quản lý, không bị CSV/Sapo ghi đè.')
                    ->schema([
                        TextInput::make('slug')->label('Slug (đường dẫn)')->prefix(Product::PATH_PREFIX)
                            ->required()->maxLength(255)
                            ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => TenantSite::unique($rule)),
                        Select::make('category_id')->label('Danh mục')
                            ->helperText('Sản phẩm hiện trên trang danh mục này và khối danh mục ở trang chủ.')
                            ->options(fn (): array => TenantSite::scope(Category::query())->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->exists('categories', 'id', fn (Exists $rule) => $rule->where('site_id', TenantSite::id())),
                        Select::make('media_id')->label('Ảnh sản phẩm (từ thư viện)')
                            ->options(fn (): array => InsertMediaAction::search(null))
                            ->getSearchResultsUsing(fn (string $search): array => InsertMediaAction::search($search))
                            ->getOptionLabelUsing(fn ($value): ?string => TenantSite::scope(Media::query())->whereKey($value)->value('alt'))
                            ->searchable()
                            ->exists('media', 'id', fn (Exists $rule) => $rule->where('site_id', TenantSite::id())),
                        Textarea::make('description')->label('Mô tả')->rows(5),
                        Toggle::make('is_active')->label('Hiện trên site')->default(true)->live(),
                        Toggle::make('is_featured')->label('Sản phẩm nổi bật (trang chủ)')->default(false),
                        Select::make('replacement_product_id')->label('Sản phẩm thay thế')
                            ->helperText('Khi sản phẩm bị ẩn: khách vào link cũ được chuyển sang sản phẩm này. Để trống thì link cũ báo "đã gỡ" (410).')
                            ->relationship('replacement', 'name', modifyQueryUsing: fn ($query, ?Product $record) => TenantSite::scope($query)
                                ->where('is_active', true)
                                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey())))
                            ->searchable()->preload()
                            ->visible(fn (Get $get): bool => ! $get('is_active')),
                        TextEntry::make('price_updated_at')->label('Ngày cập nhật giá')
                            ->state(fn (?Product $record): string => $record?->price_updated_at
                                ? $record->price_updated_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i')
                                    .' (hiện trên site đến hết ngày '.$record->priceShownUntil()?->format('d/m/Y').')'
                                : 'Chưa có giá')
                            ->visibleOn('edit'),
                    ]),
            ]);
    }
}
