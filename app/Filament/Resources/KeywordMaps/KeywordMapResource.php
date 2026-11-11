<?php

namespace App\Filament\Resources\KeywordMaps;

use App\Filament\Resources\KeywordMaps\Pages\CreateKeywordMap;
use App\Filament\Resources\KeywordMaps\Pages\EditKeywordMap;
use App\Filament\Resources\KeywordMaps\Pages\ListKeywordMaps;
use App\Filament\Resources\KeywordMaps\Schemas\KeywordMapForm;
use App\Filament\Resources\KeywordMaps\Tables\KeywordMapsTable;
use App\Models\KeywordMap;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KeywordMapResource extends Resource
{
    protected static string|\UnitEnum|null $navigationGroup = 'SEO & báo cáo';

    protected static ?int $navigationSort = 2;

    protected static ?string $model = KeywordMap::class;

    protected static ?string $slug = 'keyword-maps';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $modelLabel = 'từ khoá';

    protected static ?string $pluralModelLabel = 'Bản đồ từ khoá';

    public static function form(Schema $schema): Schema
    {
        return KeywordMapForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KeywordMapsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKeywordMaps::route('/'),
            'create' => CreateKeywordMap::route('/create'),
            'edit' => EditKeywordMap::route('/{record}/edit'),
        ];
    }
}
