<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Domain\Audit\AuditLogger;
use App\Domain\Media\Actions\DeleteMediaAsset;
use App\Domain\Media\Models\MediaAsset;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditMediaAsset extends EditRecord
{
    protected static string $resource = MediaAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label('Open file')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (MediaAsset $record) => $record->url(), shouldOpenInNewTab: true),
            Action::make('delete')
                ->label('Delete')
                ->color('danger')
                ->icon(Heroicon::OutlinedTrash)
                ->requiresConfirmation()
                ->modalDescription('The file and its resized copies are removed permanently.')
                ->visible(fn (MediaAsset $record) => Auth::user()?->can('delete', $record) ?? false)
                ->action(function (MediaAsset $record): void {
                    $actor = Auth::user();

                    try {
                        app(DeleteMediaAsset::class)->handle($record, $actor instanceof User ? $actor : null);
                    } catch (ValidationException $e) {
                        Notification::make()->title('Cannot delete')->body(collect($e->errors())->flatten()->first())->danger()->send();

                        return;
                    }

                    Notification::make()->title('File deleted')->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }

    protected function afterSave(): void
    {
        app(AuditLogger::class)->record('media.updated', $this->getRecord());
    }
}
