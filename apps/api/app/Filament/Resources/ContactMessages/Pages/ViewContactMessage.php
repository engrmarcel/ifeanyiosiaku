<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Domain\Contact\Models\ContactMessage;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/** Opening a new message marks it as read. */
class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var ContactMessage $message */
        $message = $this->getRecord();
        if ($message->status === 'new') {
            ContactMessageResource::setStatus($message, 'read');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Reply by email')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->url(fn (ContactMessage $record) => 'mailto:'.rawurlencode($record->email).'?subject='.rawurlencode('Re: '.($record->subject ?: 'Your message'))),
            ContactMessageResource::statusAction('replied', 'Mark as replied', Heroicon::OutlinedCheck),
            ActionGroup::make([
                ContactMessageResource::statusAction('spam', 'Mark as spam', Heroicon::OutlinedNoSymbol),
                ContactMessageResource::statusAction('archived', 'Archive', Heroicon::OutlinedArchiveBox),
                DeleteAction::make()->successRedirectUrl(ContactMessageResource::getUrl('index')),
            ]),
        ];
    }
}
