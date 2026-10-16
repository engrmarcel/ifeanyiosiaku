<?php

namespace App\Filament\Resources\Publications\Pages;

use App\Filament\Resources\Publications\PublicationResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePublication extends CreateRecord
{
    protected static string $resource = PublicationResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PublicationResource::prepare($data, null);
    }

    protected function getRedirectUrl(): string
    {
        // Straight to editing, where versions (files) are added.
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
