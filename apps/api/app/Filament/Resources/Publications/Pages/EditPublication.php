<?php

namespace App\Filament\Resources\Publications\Pages;

use App\Domain\Content\Models\Publication;
use App\Filament\Resources\Publications\PublicationResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPublication extends EditRecord
{
    protected static string $resource = PublicationResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Publication $publication */
        $publication = $this->getRecord();

        return PublicationResource::prepare($data, $publication);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('view_live')
                    ->label('Open live page')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn (Publication $record) => $record->status === 'published' && $record->published_at?->isPast() && ! $record->trashed())
                    ->url(fn (Publication $record) => config('platform.frontend_url').$record->path(), shouldOpenInNewTab: true),
                DeleteAction::make()->label('Move to trash'),
                RestoreAction::make(),
            ]),
        ];
    }
}
