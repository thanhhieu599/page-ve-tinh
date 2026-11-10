<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Filament\Resources\Posts\Actions\InsertMediaAction;
use App\Filament\Support\TenantSite;
use App\Models\Post;
use App\Models\Tag;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Ghi chú của biên tập')
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->iconColor('warning')
                    ->visible(fn (?Post $record): bool => filled($record?->review_note))
                    ->schema([
                        TextEntry::make('review_note')->hiddenLabel(),
                    ]),

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
                        ->helperText('Chữ thường, không dấu, nối bằng gạch ngang. Tự điền từ tiêu đề, có thể sửa.')
                        ->required()
                        ->prefix(Post::PATH_PREFIX)
                        ->maxLength(255)
                        ->rules(['regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'])
                        ->validationMessages([
                            'regex' => 'Slug chỉ gồm chữ thường không dấu, số và gạch ngang.',
                            'unique' => 'Slug này đã được dùng trên site này.',
                            'required' => 'Cần slug. Tiêu đề không sinh được slug, hãy nhập tay.',
                        ])
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule) => TenantSite::unique($rule),
                        )
                        ->live(onBlur: true)
                        ->hint(fn (?string $state): ?string => self::pathLengthWarning($state))
                        ->hintColor('danger'),

                    Select::make('locale')
                        ->label('Ngôn ngữ')
                        ->options(['vi' => 'Tiếng Việt'])
                        ->default('vi')
                        ->required()
                        ->helperText('Bản tiếng Anh sẽ mở ở giai đoạn sau.'),

                    Textarea::make('excerpt')
                        ->label('Tóm tắt')
                        ->rows(3),

                    RichEditor::make('content')
                        ->label('Nội dung')
                        ->required()
                        // Ảnh chỉ chèn qua thư viện (bắt buộc alt, gắn với site).
                        ->fileAttachments(false)
                        ->tools([
                            RichEditorTool::make(InsertMediaAction::NAME)
                                ->label('Chèn ảnh từ thư viện')
                                ->icon(Heroicon::OutlinedPhoto)
                                ->action(),
                        ])
                        ->registerActions([InsertMediaAction::make()])
                        ->toolbarButtons([
                            ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
                            ['h2', 'h3'],
                            ['alignStart', 'alignCenter', 'alignEnd'],
                            ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                            ['table', InsertMediaAction::NAME],
                            ['undo', 'redo'],
                        ]),
                ]),

                Section::make('Phân loại và tác giả')->columns(2)->schema([
                    Select::make('categories')
                        ->label('Danh mục')
                        ->helperText('Danh mục đầu tiên dùng cho breadcrumb.')
                        ->relationship('categories', 'name', modifyQueryUsing: fn (Builder $query) => TenantSite::scope($query))
                        ->multiple()
                        ->preload()
                        ->searchable(),

                    Select::make('tags')
                        ->label('Tag')
                        ->relationship('tags', 'name', modifyQueryUsing: fn (Builder $query) => TenantSite::scope($query))
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('name')->label('Tên tag')->required()->maxLength(255),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            // Tag trùng tên trong site thì dùng lại, không tạo bản sao.
                            $tag = Tag::query()->firstOrNew([
                                'site_id' => TenantSite::id(),
                                'slug' => Str::slug($data['name']),
                            ]);
                            $tag->name = $tag->exists ? $tag->name : $data['name'];
                            $tag->save();

                            return $tag->id;
                        }),

                    Select::make('author_id')
                        ->label('Tác giả')
                        ->relationship('author', 'name')
                        ->searchable()
                        ->preload(),
                ]),

                Section::make('Câu hỏi thường gặp (FAQ)')
                    ->description('Hiện cuối bài viết. Kéo thả để sắp thứ tự.')
                    ->schema([
                        Repeater::make('faqs')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                            ->addActionLabel('Thêm câu hỏi')
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('question')->label('Câu hỏi')->required()->maxLength(500),
                                Textarea::make('answer')->label('Trả lời')->required()->rows(3),
                            ]),
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
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
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
}
