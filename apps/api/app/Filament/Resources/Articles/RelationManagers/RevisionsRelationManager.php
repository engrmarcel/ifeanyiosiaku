<?php

namespace App\Filament\Resources\Articles\RelationManagers;

use App\Domain\Content\Actions\RestoreArticleRevision;
use App\Domain\Content\Models\Article;
use App\Domain\Content\Models\ContentRevision;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Every save of the article; any version can be brought back. */
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
                TextColumn::make('payload.title')->label('Title')->limit(60),
                TextColumn::make('payload.slug')->label('Address')->prefix('/articles/'),
                TextColumn::make('author.name')->label('By')->placeholder('System'),
                TextColumn::make('created_at')->label('When')->dateTime()->since(),
                TextColumn::make('note')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('restore')
                    ->label('Restore this version')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->requiresConfirmation()
                    ->modalDescription('Replaces the article\'s text, title, address, categories and tags with this version. Its publishing status stays as it is, so a published article changes on the website immediately.')
                    ->visible(function (): bool {
                        $user = Auth::user();

                        return $user instanceof User && $user->can('update', $this->getOwnerRecord());
                    })
                    ->action(function (ContentRevision $record) {
                        $actor = Auth::user();
                        /** @var Article $article */
                        $article = $this->getOwnerRecord();

                        try {
                            app(RestoreArticleRevision::class)->handle($article, $record, $actor instanceof User ? $actor : null);
                        } catch (ValidationException $e) {
                            Notification::make()->title('Not possible')->body(collect($e->errors())->flatten()->first())->danger()->send();

                            return;
                        }

                        Notification::make()->title("Version {$record->number} restored")->success()->send();
                        $this->redirect(ArticleResource::getUrl('edit', ['record' => $article]));
                    }),
            ]);
    }
}
