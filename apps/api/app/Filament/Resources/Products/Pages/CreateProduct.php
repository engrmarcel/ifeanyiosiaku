<?php

namespace App\Filament\Resources\Products\Pages;

use App\Domain\Commerce\Models\Product;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use SavesProducts;

    protected static string $resource = ProductResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareProductData($data, null);
    }

    protected function afterCreate(): void
    {
        /** @var Product $product */
        $product = $this->getRecord();
        $this->afterProductSaved($product, 'product.created');
    }

    protected function getRedirectUrl(): string
    {
        // Straight to editing, where files are uploaded.
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
