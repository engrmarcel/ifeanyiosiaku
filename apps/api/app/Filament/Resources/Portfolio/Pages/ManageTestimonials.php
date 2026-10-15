<?php

namespace App\Filament\Resources\Portfolio\Pages;

use App\Filament\Resources\Portfolio\TestimonialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTestimonials extends ManageRecords
{
    protected static string $resource = TestimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add testimonial')];
    }
}
