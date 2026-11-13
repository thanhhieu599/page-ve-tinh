<?php

namespace App\Support\Crm;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/** Thông báo trong CRM (chuông góc phải + Nhật ký thông báo). */
final class CrmNotifier
{
    public static function leadAssigned(Lead $lead, User $to): void
    {
        Notification::make()
            ->title('Bạn được giao khách hàng tiềm năng')
            ->body(trim(($lead->company ?: $lead->name).' · '.($lead->site?->domain ?? $lead->channel?->getLabel())).': '.str($lead->need)->limit(120))
            ->icon('heroicon-o-user-plus')
            ->actions([Action::make('open')->label('Mở lead')->url('/crm/khach-hang-tiem-nang/'.$lead->id.'/edit')])
            ->sendToDatabase($to);
    }

    public static function taskAssigned(Task $task, User $to): void
    {
        Notification::make()
            ->title('Việc mới: '.$task->title)
            ->body('Hạn: '.$task->due_at?->timezone(config('app.display_timezone'))->format('d/m/Y H:i'))
            ->icon('heroicon-o-clipboard-document-check')
            ->actions([Action::make('open')->label('Mở việc')->url('/crm/viec-can-lam')])
            ->sendToDatabase($to);
    }
}
