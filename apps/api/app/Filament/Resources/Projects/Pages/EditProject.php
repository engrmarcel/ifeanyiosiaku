<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Domain\Portfolio\Models\Project;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_live')
                ->label('Open on website')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible(fn (Project $record) => $record->status === 'published')
                ->url(fn (Project $record) => config('platform.frontend_url').'/projects/'.$record->slug, shouldOpenInNewTab: true),
            DeleteAction::make()->label('Move to trash'),
        ];
    }
}
