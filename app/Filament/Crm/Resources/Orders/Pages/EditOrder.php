<?php

namespace App\Filament\Crm\Resources\Orders\Pages;

use App\Enums\Crm\OrderStatus;
use App\Filament\Crm\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    private function order(): Order
    {
        $order = $this->getRecord();
        assert($order instanceof Order);

        return $order;
    }

    protected function afterSave(): void
    {
        $this->order()->recalculateTotals();
        $this->order()->save(); // cập nhật tình trạng thanh toán theo tổng mới
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('payment')->label('Ghi nhận thanh toán')->icon(Heroicon::OutlinedBanknotes)->color('success')
                ->visible(fn (): bool => $this->order()->outstanding() > 0 && $this->order()->status !== OrderStatus::Cancelled)
                ->schema([TextInput::make('amount')->label('Số tiền thu (đ)')->integer()->minValue(1)->required()
                    ->default(fn (): int => $this->order()->outstanding())])
                ->action(function (array $data): void {
                    $order = $this->order();
                    $order->forceFill(['paid_amount' => (int) $order->paid_amount + (int) $data['amount']])->save();
                    Notification::make()->title('Đã ghi nhận '.Order::money((int) $data['amount']))->success()->send();
                    $this->refreshFormData(['paid_amount', 'payment_status']);
                }),
            Action::make('complete')->label('Đã giao xong')->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (): bool => ! in_array($this->order()->status, [OrderStatus::Completed, OrderStatus::Cancelled], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->order()->forceFill(['status' => OrderStatus::Completed])->save();
                    $this->refreshFormData(['status', 'delivered_at']);
                }),
        ];
    }
}
