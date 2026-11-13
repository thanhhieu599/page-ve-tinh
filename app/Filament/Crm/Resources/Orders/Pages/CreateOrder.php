<?php

namespace App\Filament\Crm\Resources\Orders\Pages;

use App\Filament\Crm\Resources\Orders\OrderResource;
use App\Filament\Crm\Support\CrmFields;
use App\Models\Customer;
use App\Models\Order;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function afterFill(): void
    {
        // Mở từ trang khách hàng: điền sẵn khách và người liên hệ chính.
        $customer = Customer::query()->visibleTo(CrmFields::user())->find(request()->query('customer'));
        if ($customer) {
            $this->form->fill([...$this->form->getRawState(), 'customer_id' => $customer->id, 'contact_id' => $customer->primaryContact?->id]);
        }
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_id'] ??= CrmFields::user()->id;

        return $data;
    }

    protected function afterCreate(): void
    {
        $quote = $this->getRecord();
        assert($quote instanceof Order);
        $quote->recalculateTotals();
    }
}
