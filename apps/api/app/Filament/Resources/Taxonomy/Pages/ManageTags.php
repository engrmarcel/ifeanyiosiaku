<?php

namespace App\Filament\Resources\Taxonomy\Pages;

use App\Filament\Resources\Taxonomy\TagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTags extends ManageRecords
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add tag')];
    }
}
