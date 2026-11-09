<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Models\Post;
use App\Models\Site;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Nội dung')->schema([
                    TextInput::make('title')
                        ->label('Tiêu đề')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                            // Chỉ tự đặt slug khi người viết chưa sửa slug bằng tay.
                            if (blank($get('slug')) || $get('slug') === Str::slug((string) $old)) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),

                    TextInput::make('slug')
                        ->label('Slug (đường dẫn)')
                        ->helperText('Chữ thường, không dấu, nối bằng gạch ngang. Để trống sẽ tự sinh từ tiêu đề.')
                        ->prefix(Post::PATH_PREFIX)
                        ->maxLength(255)
                        ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                        ->validationMessages([
                            'regex' => 'Slug chỉ gồm chữ thường không dấu, số và gạch ngang.',
                            'unique' => 'Slug này đã được dùng trên site này.',
                        ])
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule) => $rule->where('site_id', self::currentSiteId()),
                        )
                        ->live(onBlur: true)
                        ->hint(fn (?string $state): ?string => self::pathLengthWarning($state))
                        ->hintColor('danger'),

                    Textarea::make('excerpt')
                        ->label('Tóm tắt')
                        ->rows(3),

                    RichEditor::make('content')
                        ->label('Nội dung')
                        ->required(),
                ]),

                Section::make('SEO')->schema([
                    TextInput::make('seo_title')
                        ->label('Tiêu đề SEO')
                        ->helperText('Nên dưới 60 ký tự. Để trống sẽ dùng tiêu đề bài.')
                        ->maxLength(255),

                    Textarea::make('meta_description')
                        ->label('Meta description')
                        ->helperText('Nên 120–160 ký tự.')
                        ->maxLength(300)
                        ->rows(2),

                    FileUpload::make('og_image')
                        ->label('Ảnh chia sẻ (OG image)')
                        ->image()
                        ->disk('public')
                        ->directory('og-images'),
                ]),
            ]);
    }

    /**
     * Cảnh báo (không chặn lưu) khi cả đường dẫn bài dài từ 75 ký tự trở lên.
     */
    public static function pathLengthWarning(?string $slug): ?string
    {
        $length = mb_strlen(Post::PATH_PREFIX.$slug);

        if (blank($slug) || $length < Post::MAX_PATH_LENGTH) {
            return null;
        }

        return "Đường dẫn dài {$length} ký tự, nên dưới ".Post::MAX_PATH_LENGTH.'.';
    }

    private static function currentSiteId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Site ? $tenant->id : null;
    }
}
