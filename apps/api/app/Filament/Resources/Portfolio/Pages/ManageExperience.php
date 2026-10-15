<?php

namespace App\Filament\Resources\Portfolio\Pages;

use App\Filament\Resources\Portfolio\ExperienceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExperience extends ManageRecords
{
    protected static string $resource = ExperienceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add experience entry')];
    }
}
