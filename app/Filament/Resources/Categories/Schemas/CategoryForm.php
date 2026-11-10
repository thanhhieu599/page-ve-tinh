<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\TenantSite;
use App\Models\Category;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Danh mục')->schema([
                    TextInput::make('name')->label('Tên')->required()->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                            if (blank($get('slug')) || $get('slug') === Str::slug((string) $old)) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')->label('Slug (đường dẫn)')->prefix(Category::PATH_PREFIX)
                        ->required()->maxLength(255)
                        ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => TenantSite::unique($rule)),
                    Select::make('parent_id')->label('Danh mục cha')
                        ->relationship('parent', 'name', modifyQueryUsing: fn (Builder $query, ?Category $record) => TenantSite::scope($query)
                            ->when($record, fn (Builder $q) => $q->whereKeyNot($record->selfAndDescendantIds())))
                        ->searchable()->preload(),
                    Textarea::make('description')->label('Mô tả')->rows(3),
                ]),

                Section::make('SEO')->schema([
                    TextInput::make('seo_title')->label('Tiêu đề SEO')->maxLength(255),
                    Textarea::make('meta_description')->label('Meta description')->maxLength(300)->rows(2),
                ]),

                Section::make('Kiểm soát nội dung')->schema([
                    Toggle::make('requires_expert_review')
                        ->label('Yêu cầu duyệt chuyên môn')
                        ->helperText('Bài thuộc danh mục này chỉ người có quyền duyệt chuyên môn mới đăng/sửa khi đang hiện. Chỉ Admin đổi được.')
                        ->disabled(fn (): bool => ! (auth()->user() instanceof User && auth()->user()->isAdmin())),
                ]),
            ]);
    }
}
