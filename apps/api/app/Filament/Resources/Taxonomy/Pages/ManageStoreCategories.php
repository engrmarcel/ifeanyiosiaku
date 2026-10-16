<?php

namespace App\Filament\Resources\Taxonomy\Pages;

use App\Domain\Content\Models\Category;
use App\Filament\Resources\Taxonomy\StoreCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStoreCategories extends ManageRecords
{
    protected static string $resource = StoreCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add store category')
                ->mutateDataUsing(fn (array $data): array => [...$data, 'type' => Category::TYPE_PRODUCT]),
        ];
    }
}
