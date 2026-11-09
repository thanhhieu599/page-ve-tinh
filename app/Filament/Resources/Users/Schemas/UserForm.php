<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $roleOptions = [];
        foreach (Role::cases() as $role) {
            $roleOptions[$role->value] = $role->label();
        }

        return $schema
            ->columns(1)
            ->components([
                Section::make('Tài khoản')->schema([
                    TextInput::make('name')->label('Họ tên')->required()->maxLength(255),
                    TextInput::make('email')->label('Email')->email()->required()->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('password')->label('Mật khẩu')->password()->revealable()
                        ->minLength(12)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText('Ít nhất 12 ký tự. Khi sửa, để trống nếu không đổi mật khẩu.'),
                ]),

                Section::make('Phân quyền')->schema([
                    Select::make('role')->label('Vai trò')->options($roleOptions)->required()
                        ->dehydrated(false)
                        ->helperText('Admin thấy mọi site. Biên tập và Người viết chỉ thấy các site được gán.'),
                    CheckboxList::make('sites')->label('Site được gán')
                        ->relationship('sites', 'name')
                        ->columns(2),
                ]),
            ]);
    }
}
