<?php

namespace App\Filament\Resources\RequiredPages;

use App\Filament\Resources\RequiredPages\Pages\EditRequiredPage;
use App\Filament\Resources\RequiredPages\Pages\ListRequiredPages;
use App\Filament\Resources\RequiredPages\Schemas\RequiredPageForm;
use App\Filament\Resources\RequiredPages\Tables\RequiredPagesTable;
use App\Models\RequiredPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Ba trang bắt buộc của site: chỉ sửa và bật hiển thị, không thêm/xoá (tạo sẵn khi tạo site). */
class RequiredPageResource extends Resource
{
    protected static ?string $model = RequiredPage::class;

    protected static ?string $slug = 'required-pages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'trang bắt buộc';

    protected static ?string $pluralModelLabel = 'Trang bắt buộc';

    public static function form(Schema $schema): Schema
    {
        return RequiredPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequiredPagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequiredPages::route('/'),
            'edit' => EditRequiredPage::route('/{record}/edit'),
        ];
    }
}
