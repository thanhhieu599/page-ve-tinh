<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /** Giá vừa nhập trên form; chỉ ghi qua Product::setPrice() để ngày giá luôn đi kèm. */
    private ?int $submittedPrice = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->submittedPrice = filled($data['price'] ?? null) ? (int) $data['price'] : null;
        unset($data['price']);

        return $data;
    }

    protected function afterSave(): void
    {
        $product = $this->product();

        if ($this->submittedPrice !== $product->price) {
            $product->setPrice($this->submittedPrice);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmPrice')
                ->label('Xác nhận giá vẫn đúng')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible(fn (): bool => $this->product()->price !== null)
                ->requiresConfirmation()
                ->modalDescription('Chỉ xác nhận khi đã kiểm tra lại giá với Sapo/nhà cung cấp. Giá giữ nguyên, ngày cập nhật giá đổi thành hôm nay, giá tiếp tục hiện trên site thêm '.Product::PRICE_VALID_DAYS.' ngày.')
                ->action(function (): void {
                    $this->product()->confirmPrice();
                    $this->refreshFormData(['price_updated_at']);
                    Notification::make()->title('Đã xác nhận giá')->success()->send();
                }),
            DeleteAction::make(),
        ];
    }

    private function product(): Product
    {
        $record = $this->getRecord();
        assert($record instanceof Product);

        return $record;
    }
}
