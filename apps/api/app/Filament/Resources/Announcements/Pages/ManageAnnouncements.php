<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Domain\Settings\SettingsRepository;
use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAnnouncements extends ManageRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New announcement')];
    }

    /** The global switch overrides every announcement, so say clearly when it is off. */
    public function getSubheading(): ?string
    {
        return app(SettingsRepository::class)->bool('announcements.enabled')
            ? 'The popup is switched ON in Site settings: running announcements are shown to visitors.'
            : 'The popup is switched OFF (System → Site settings → Announcements), so no announcement is shown, even active or scheduled ones.';
    }
}
