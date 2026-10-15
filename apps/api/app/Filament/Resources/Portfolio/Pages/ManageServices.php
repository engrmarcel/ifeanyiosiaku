<?php

namespace App\Filament\Resources\Portfolio\Pages;

use App\Filament\Resources\Portfolio\ServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageServices extends ManageRecords
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add service')->slideOver()];
    }
}
