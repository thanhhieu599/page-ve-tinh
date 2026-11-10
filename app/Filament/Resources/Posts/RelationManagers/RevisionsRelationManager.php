<?php

namespace App\Filament\Resources\Posts\RelationManagers;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\PostRevision;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Lịch sử sửa bài: xem các bản đã lưu và khôi phục một bản cũ.
 */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Lịch sử sửa';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Thời điểm')
                    ->dateTime('d/m/Y H:i', timezone: config('app.display_timezone')),
                TextColumn::make('user.name')->label('Người sửa')->placeholder('Hệ thống'),
                TextColumn::make('title')->label('Tiêu đề')->limit(60),
                TextColumn::make('content')->label('Nội dung')
                    ->formatStateUsing(fn (string $state): string => Str::limit(strip_tags($state), 120)),
            ])
            ->recordActions([
                Action::make('restore')
                    ->label('Khôi phục bản này')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->requiresConfirmation()
                    ->modalDescription('Tiêu đề và nội dung bài sẽ trở về bản này. Bản hiện tại vẫn được giữ trong lịch sử.')
                    ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->action(function (PostRevision $record): void {
                        $post = $this->getOwnerRecord();
                        assert($post instanceof Post);
                        Gate::authorize('update', $post);

                        $post->restoreRevision($record);
                        Notification::make()->title('Đã khôi phục bản cũ')->success()->send();

                        // Tải lại trang để form hiện bản vừa khôi phục, tránh lưu đè bằng dữ liệu cũ.
                        $this->redirect(PostResource::getUrl('edit', ['record' => $post]));
                    }),
            ]);
    }
}
