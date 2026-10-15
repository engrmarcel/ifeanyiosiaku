<?php

namespace App\Filament\Resources\Portfolio\Pages;

use App\Filament\Resources\Portfolio\CertificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCertifications extends ManageRecords
{
    protected static string $resource = CertificationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add certification')];
    }
}
