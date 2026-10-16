<?php

namespace App\Filament\Resources\Taxonomy\Pages;

use App\Domain\Content\Models\Category;
use App\Filament\Resources\Taxonomy\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add category')
                ->mutateDataUsing(fn (array $data): array => [...$data, 'type' => Category::TYPE_ARTICLE]),
        ];
    }
}
