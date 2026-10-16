<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Domain\Settings\SettingsRepository;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    public function getSubheading(): ?string
    {
        $recipient = app(SettingsRepository::class)->get('contact.recipient_email');

        return is_string($recipient) && $recipient !== ''
            ? "New messages are also emailed to {$recipient}."
            : 'New messages are not emailed to anyone yet. Set an address in System → Site settings → Contact form.';
    }
}
