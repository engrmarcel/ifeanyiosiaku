<?php

namespace App\Filament\Resources\Products\Pages;

use App\Domain\Commerce\Models\Product;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProduct extends EditRecord
{
    use SavesProducts;

    protected static string $resource = ProductResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Product $product */
        $product = $this->getRecord();

        return $this->prepareProductData($data, $product);
    }

    protected function afterSave(): void
    {
        /** @var Product $product */
        $product = $this->getRecord();
        $this->afterProductSaved($product, 'product.updated');
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('view_live')
                    ->label('Open in the store')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn (Product $record) => $record->isLive() && ! $record->trashed())
                    ->url(fn (Product $record) => config('platform.frontend_url').$record->path(), shouldOpenInNewTab: true),
                DeleteAction::make()->label('Move to trash'),
                RestoreAction::make(),
            ]),
        ];
    }
}
