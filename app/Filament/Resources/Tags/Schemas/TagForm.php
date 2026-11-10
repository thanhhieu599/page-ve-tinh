<?php

namespace App\Filament\Resources\Tags\Schemas;

use App\Filament\Support\TenantSite;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Tên tag')->required()->maxLength(255),
            TextInput::make('slug')->label('Slug')->maxLength(255)
                ->helperText('Để trống sẽ tự sinh từ tên.')
                ->rules(['nullable', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => TenantSite::unique($rule)),
        ]);
    }
}
