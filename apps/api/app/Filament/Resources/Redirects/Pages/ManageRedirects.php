<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Domain\Cms\Actions\SaveRedirect;
use App\Domain\Cms\Models\Redirect;
use App\Filament\Resources\Pages\Pages\PageFormErrors;
use App\Filament\Resources\Redirects\RedirectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRedirects extends ManageRecords
{
    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add redirect')
                ->using(fn (array $data): Redirect => PageFormErrors::map(
                    fn () => app(SaveRedirect::class)->handle(null, $data, RedirectResource::actor()),
                    PageFormErrors::MODAL,
                )),
        ];
    }
}
