<?php

namespace App\Filament\Crm\Resources\Customers\RelationManagers;

use App\Enums\Crm\ContactRole;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Người liên hệ: ai mua hàng, ai duyệt kỹ thuật (QA/EHS), ai nhận hàng. */
class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Người liên hệ';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Họ tên')->required()->maxLength(255),
            Select::make('role')->label('Vai trò')->options(ContactRole::class),
            TextInput::make('title')->label('Chức vụ')->maxLength(255),
            TextInput::make('phone')->label('Điện thoại')->tel()->maxLength(50),
            TextInput::make('email')->label('Email')->email()->maxLength(255),
            TextInput::make('zalo')->label('Zalo')->maxLength(50),
            Toggle::make('is_primary')->label('Liên hệ chính'),
            Textarea::make('notes')->label('Ghi chú')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Họ tên')->description(fn ($record) => $record->title),
            TextColumn::make('role')->label('Vai trò')->badge(),
            TextColumn::make('phone')->label('Điện thoại'),
            TextColumn::make('email')->label('Email'),
            TextColumn::make('zalo')->label('Zalo')->toggleable(),
            IconColumn::make('is_primary')->label('Chính')->boolean(),
        ])
            ->headerActions([CreateAction::make()->label('Thêm người liên hệ')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
