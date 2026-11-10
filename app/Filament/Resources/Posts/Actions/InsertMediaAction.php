<?php

namespace App\Filament\Resources\Posts\Actions;

use App\Filament\Resources\Media\Schemas\MediaForm;
use App\Filament\Support\TenantSite;
use App\Models\Media;
use App\Support\MediaLibrary;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Validation\Rules\Exists;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Chèn ảnh vào bài: chọn ảnh có sẵn trong thư viện của site, hoặc tải ảnh mới
 * (bắt buộc alt; ảnh mới cũng vào thư viện). Thay cho nút đính kèm mặc định,
 * vốn không bắt buộc alt và không gắn ảnh với site.
 */
class InsertMediaAction
{
    public const NAME = 'insertMedia';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Chèn ảnh từ thư viện')
            ->modalHeading('Chèn ảnh')
            ->modalWidth(Width::Large)
            ->schema([
                Select::make('media_id')
                    ->label('Chọn ảnh có sẵn')
                    ->options(fn (): array => TenantSite::scope(Media::query())
                        ->latest()->limit(200)->pluck('alt', 'id')->all())
                    ->searchable()
                    ->live()
                    ->exists('media', 'id', fn (Exists $rule) => $rule->where('site_id', TenantSite::id()))
                    ->requiredWithout('upload'),
                FileUpload::make('upload')
                    ->label('Hoặc tải ảnh mới')
                    ->image()
                    ->acceptedFileTypes(MediaLibrary::ACCEPTED_TYPES)
                    ->maxSize(10240)
                    ->storeFiles(false)
                    ->hidden(fn (Get $get): bool => filled($get('media_id'))),
                MediaForm::altInput()
                    ->required(fn (Get $get): bool => filled($get('upload')))
                    ->hidden(fn (Get $get): bool => filled($get('media_id'))),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component): void {
                $media = filled($data['media_id'] ?? null)
                    ? TenantSite::scope(Media::query())->findOrFail($data['media_id'])
                    : self::storeUpload($data);

                $component->runCommands(
                    [
                        EditorCommand::make('insertContent', arguments: [[
                            'type' => 'image',
                            'attrs' => ['src' => $media->url(), 'alt' => $media->alt],
                        ]]),
                    ],
                    editorSelection: $arguments['editorSelection'] ?? null,
                );
            });
    }

    /** @param  array<string, mixed>  $data */
    private static function storeUpload(array $data): Media
    {
        $upload = $data['upload'] ?? null;
        $file = is_array($upload) ? reset($upload) : $upload;
        assert($file instanceof TemporaryUploadedFile);

        return MediaLibrary::store($file->getRealPath(), (string) $data['alt'], (int) TenantSite::id());
    }
}
