<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submitForReview')
                ->label('Gửi duyệt')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->authorize('submitForReview')
                ->action(function (): void {
                    // Lưu phần đang sửa trước khi gửi, để biên tập thấy bản mới nhất.
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $this->post()->submitForReview();
                    Notification::make()->title('Đã gửi duyệt')->success()->send();
                }),

            Action::make('publish')
                ->label('Duyệt & đăng')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->authorize('publish')
                ->schema([
                    DateTimePicker::make('published_at')
                        ->label('Thời điểm đăng')
                        ->helperText('Để thời điểm trong tương lai để hẹn giờ đăng.')
                        ->seconds(false)
                        // Người dùng nhập giờ Việt Nam; dữ liệu lưu UTC.
                        ->timezone(config('app.display_timezone'))
                        ->default(now())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    // Danh mục vừa lưu có thể làm bài cần duyệt chuyên môn: kiểm tra lại quyền đăng.
                    $post = $this->post();
                    $post->load('categories');
                    if (Gate::denies('publish', $post)) {
                        Notification::make()->title('Bài thuộc danh mục cần duyệt chuyên môn')
                            ->body('Đã lưu thay đổi nhưng chưa đăng. Cần người có quyền duyệt chuyên môn đăng bài này.')
                            ->danger()->send();

                        return;
                    }

                    $post->publish(Carbon::parse($data['published_at']));
                    Notification::make()
                        ->title($this->post()->isScheduled() ? 'Đã hẹn giờ đăng' : 'Đã đăng bài')
                        ->success()->send();
                }),

            Action::make('returnToDraft')
                ->label('Trả về nháp')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('warning')
                ->authorize('returnToDraft')
                ->schema([
                    Textarea::make('note')->label('Ghi chú cho người viết')->rows(3),
                ])
                ->action(function (array $data): void {
                    $this->post()->returnToDraft($data['note'] ?? null);
                    Notification::make()->title('Đã trả về nháp')->success()->send();
                }),

            ActionGroup::make([
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ]),
        ];
    }

    /**
     * Bài đang hiện trên site: người không có quyền duyệt chuyên môn không được đưa nó
     * vào danh mục cần duyệt chuyên môn (sẽ thành nội dung PPE chưa qua duyệt).
     */
    protected function beforeSave(): void
    {
        $post = $this->post();
        $user = auth()->user();
        $categoryIds = $this->data['categories'] ?? [];

        if ($post->status !== PostStatus::Published || ! $user instanceof User || $user->isExpertReviewer()) {
            return;
        }

        if (Category::anyUnderExpertReview(is_array($categoryIds) ? $categoryIds : [])) {
            Notification::make()->title('Không lưu được')
                ->body('Bài đang hiện trên site không thể chuyển vào danh mục cần duyệt chuyên môn nếu bạn không có quyền duyệt chuyên môn.')
                ->danger()->send();

            $this->halt();
        }
    }

    private function post(): Post
    {
        $record = $this->getRecord();
        assert($record instanceof Post);

        return $record;
    }
}
