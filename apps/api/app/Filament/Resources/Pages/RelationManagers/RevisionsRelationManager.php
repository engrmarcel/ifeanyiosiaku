<?php

namespace App\Filament\Resources\Pages\RelationManagers;

use App\Domain\Cms\Actions\RestorePageRevision;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageRevision;
use App\Filament\Resources\Pages\PageResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/** Version history: every save, publish and restore. Any version can be restored into the draft. */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Version history';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('number', 'desc')
            ->columns([
                TextColumn::make('number')->label('#'),
                TextColumn::make('kind')->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'publish' => 'Published', 'restore' => 'Restored', default => 'Draft saved'
                    })
                    ->color(fn (string $state) => match ($state) {
                        'publish' => 'success', 'restore' => 'info', default => 'gray'
                    }),
                TextColumn::make('title'),
                TextColumn::make('slug')->prefix('/'),
                TextColumn::make('author.name')->label('By')->placeholder('System'),
                TextColumn::make('created_at')->label('When')->dateTime()->since(),
                TextColumn::make('note')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('restore')
                    ->label('Restore to draft')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->requiresConfirmation()
                    ->modalDescription('Replaces the current draft with this version. The live page does not change until you publish.')
                    ->action(function (PageRevision $record) {
                        $actor = Auth::user();
                        /** @var Page $page */
                        $page = $this->getOwnerRecord();
                        app(RestorePageRevision::class)->handle($page, $record, $actor instanceof User ? $actor : null);
                        Notification::make()->title("Version {$record->number} restored to the draft")->success()->send();
                        $this->redirect(PageResource::getUrl('edit', ['record' => $page]));
                    }),
            ]);
    }
}
