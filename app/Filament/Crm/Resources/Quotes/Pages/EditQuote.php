<?php

namespace App\Filament\Crm\Resources\Quotes\Pages;

use App\Enums\Crm\QuoteStatus;
use App\Filament\Crm\Resources\Orders\OrderResource;
use App\Filament\Crm\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    private function quote(): Quote
    {
        $quote = $this->getRecord();
        assert($quote instanceof Quote);

        return $quote;
    }

    protected function afterSave(): void
    {
        $this->quote()->recalculateTotals();
        $this->refreshFormData(['subtotal', 'vat_amount', 'total']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')->label('In / Lưu PDF')->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->url(fn (): string => route('crm.quotes.print', $this->quote()), shouldOpenInNewTab: true),
            Action::make('sent')->label('Đã gửi khách')->icon(Heroicon::OutlinedPaperAirplane)->color('info')
                ->visible(fn (): bool => $this->quote()->status === QuoteStatus::Draft)
                ->action(function (): void {
                    $this->quote()->forceFill(['status' => QuoteStatus::Sent])->save();
                    $this->refreshFormData(['status']);
                }),
            Action::make('negotiating')->label('Đang đàm phán')->color('warning')
                ->visible(fn (): bool => $this->quote()->status === QuoteStatus::Sent)
                ->action(function (): void {
                    $this->quote()->forceFill(['status' => QuoteStatus::Negotiating])->save();
                    $this->refreshFormData(['status']);
                }),
            Action::make('won')->label('Thắng → tạo đơn hàng')->icon(Heroicon::OutlinedTrophy)->color('success')
                ->visible(fn (): bool => $this->quote()->isOpen())
                ->requiresConfirmation()
                ->modalDescription('Tạo đơn hàng với đúng các dòng hàng của báo giá này.')
                ->action(function () {
                    $order = $this->quote()->markWon();
                    Notification::make()->title('Đã tạo đơn hàng '.$order->number)->success()->send();

                    return redirect(OrderResource::getUrl('edit', ['record' => $order]));
                }),
            Action::make('lost')->label('Thua')->icon(Heroicon::OutlinedXCircle)->color('danger')
                ->visible(fn (): bool => $this->quote()->isOpen())
                ->schema([TextInput::make('reason')->label('Lý do thua (giá, thời gian giao, tiêu chuẩn, đối thủ…)')->required()->maxLength(255)])
                ->action(function (array $data): void {
                    $this->quote()->markLost($data['reason']);
                    $this->refreshFormData(['status', 'lost_reason']);
                }),
        ];
    }
}
