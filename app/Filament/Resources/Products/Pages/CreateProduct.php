<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\TenantSite;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        $price = $data['price'] ?? null;
        unset($data['price']);

        $product = new Product($data);
        $product->site_id = (int) TenantSite::id();
        $product->save();

        if (filled($price)) {
            $product->setPrice((int) $price);
        }

        return $product;
    }
}
