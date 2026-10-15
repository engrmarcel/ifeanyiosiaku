<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Domain\Cms\Actions\SavePageDraft;
use App\Domain\Cms\Models\Page;
use App\Filament\Resources\Pages\PageResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected static bool $canCreateAnother = false;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = Auth::user();

        return PageFormErrors::map(fn () => app(SavePageDraft::class)->handle(new Page, $data, $actor instanceof User ? $actor : null));
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Draft saved. Use Preview to check it, then Publish when ready.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
