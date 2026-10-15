<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Domain\Cms\Actions\DeletePage;
use App\Domain\Cms\Actions\PublishPage;
use App\Domain\Cms\Actions\SavePageDraft;
use App\Domain\Cms\Actions\SetHomepage;
use App\Domain\Cms\Actions\UnpublishPage;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\PreviewToken;
use App\Filament\Resources\Pages\PageResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * "Save" stores the working draft only. Visitors see changes after Publish.
 */
class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Page $record */
        return PageFormErrors::map(fn () => app(SavePageDraft::class)->handle($record, $data, $this->actor()));
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Draft saved (not yet visible to visitors)';
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Save draft');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (Page $record) => config('platform.frontend_url').'/preview?token='.app(PreviewToken::class)->issue($record), shouldOpenInNewTab: true)
                ->tooltip('Opens the saved draft on the website. Save first to include your latest changes.'),
            Action::make('publish')
                ->label('Publish')
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->requiresConfirmation()
                ->modalHeading('Publish this page?')
                ->modalDescription('The saved draft becomes visible on the website. Unsaved changes in this form are not included.')
                ->visible(fn (Page $record) => $this->actor()?->can('publish', $record) ?? false)
                ->action(fn (Page $record) => $this->run(fn () => app(PublishPage::class)->handle($record, $this->actor()), 'Published — the page is live')),
            ActionGroup::make([
                Action::make('view_live')
                    ->label('Open live page')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn (Page $record) => $record->isLive())
                    ->url(fn (Page $record) => config('platform.frontend_url').$record->path(), shouldOpenInNewTab: true),
                Action::make('set_homepage')
                    ->label('Make this the homepage')
                    ->icon(Heroicon::OutlinedHome)
                    ->requiresConfirmation()
                    ->visible(fn (Page $record) => ! $record->is_homepage && ($this->actor()?->can('publish', $record) ?? false))
                    ->action(fn (Page $record) => $this->run(fn () => app(SetHomepage::class)->handle($record, $this->actor()), 'This page is now the homepage')),
                Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Page $record) => $record->isLive() && ! $record->is_homepage && ($this->actor()?->can('publish', $record) ?? false))
                    ->action(fn (Page $record) => $this->run(fn () => app(UnpublishPage::class)->handle($record, $this->actor()), 'Unpublished — the page is no longer on the website')),
                Action::make('delete')
                    ->label('Move to trash')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Page $record) => ($this->actor()?->can('delete', $record) ?? false) && ! $record->trashed())
                    ->action(function (Page $record) {
                        $this->run(fn () => app(DeletePage::class)->handle($record, $this->actor()), 'Moved to trash');
                        $this->redirect($this->getResource()::getUrl('index'));
                    }),
            ]),
        ];
    }

    private function run(callable $operation, string $success): void
    {
        try {
            $operation();
            $this->getRecord()->refresh();
            Notification::make()->title($success)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Not possible yet')->body(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
